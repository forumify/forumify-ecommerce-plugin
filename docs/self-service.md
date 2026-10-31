# Customer self-service

What a buyer can do with a purchase after they made it: settle a failed payment, download the invoice,
cancel a subscription, and ask for a refund. The buyer's pages themselves are in
[checkout-and-orders.md](checkout-and-orders.md#buyer-pages).

> **Decision: every action the buyer takes is the same action an admin has.** Cancelling goes through
> `SubscriptionCancellationService`, refunding through `RefundService`, exactly as in the admin. The buyer's
> pages are a second entrance, never a second implementation.

## Provider capabilities

Three of the four features need something from the payment provider, and not every provider can do all of
them. `PaymentProviderInterface` gained:

```php
getCapabilities(): list<PaymentProviderCapability>   // Refund | Invoice | HostedPayment
refund(Order): list<PaymentEvent>
getHostedPaymentUrl(Order): ?string
getInvoice(Order): ?InvoiceDocument                  // ::url(...) or ::file(...)
```

> **Decision: capabilities are declared, not discovered.** The UI has to know whether to show "Download
> invoice" before it renders. Asking the provider (an API call) on every page render to find out would be
> absurd, so a provider states what it can do and the actual call happens when the buyer clicks.
> `PaymentProviderRegistry::findCapableFor($order, $capability)` is the one lookup everything uses.

Stripe declares all three. The fake provider does too — including a hand-rolled one-page PDF, so the
download path is exercised in dev and in tests without a Stripe account.

## Past due: settling a payment (`/store/order/{reference}/pay`)

The route asks the provider for a URL at click time and redirects. **Provider URLs are never stored** —
Stripe's hosted invoice page and portal sessions expire, so a cached link would rot.

For Stripe that is the failed invoice's `hosted_invoice_url`, which lets the buyer pay *and* replace the
card — the usual reason a renewal fails. The button shows up on the pending renewal's order page
("Update payment method") and, for an unpaid first payment, as "Pay now".

The renewal order itself stays `Pending`: past due is a property of the **subscription**, and duplicating it
onto the order would give two places to disagree.

## Invoices (`/store/order/{reference}/invoice`)

`InvoiceDocument` is either a URL (redirect) or contents (streamed as an attachment). Stripe returns
`invoice_pdf`; a provider that generates its own file returns the bytes.

Stripe only issues invoices for one-time payments when the Checkout Session asks for them, so
`startCheckout()` sets `invoice_creation.enabled` in payment mode. Subscriptions always invoice.

> **Decision: invoices are fetched, never stored.** The provider is the bookkeeping record. Copying PDFs
> into our own storage would mean a second copy to keep correct, back up and delete on request.

## Cancelling a subscription

The buyer cancels from `/store/subscription/{id}`, always **at the end of the paid period**: they paid for
it, they keep it until it runs out, and no money goes back. Cancelling right away stays an admin action.

The provider is told immediately, so the subscription becomes `Cancelled` at once.

### Cancellations that need an admin

For a product with a **manual fulfillment type**, cancelling is not the end of the work: someone has to
close the thing down, just like someone had to hand it over. `CancellationRequest` (one per subscription) is
that work item.

- `CancellationSubscriber` opens one on `SubscriptionCancelledEvent` **and** `SubscriptionEndedEvent`, so it
  appears no matter who cancelled — the buyer, an admin, or the provider's own dashboard. An immediate
  cancellation only produces "ended", which is exactly when the teardown is most urgent.
- Everyone with the product's `fulfillmentRole` is notified, the same crowd that fulfills its orders.
- The buyer's plan card reads "Cancellation requested" until an admin marks it handled on the subscription
  page. The [dashboard](dashboard.md) lists the pending ones under "Cancellations to handle".
- `Subscription::resume()` drops the request (`orphanRemoval`): a buyer who un-cancels through the
  provider's portal leaves no phantom task behind.
- `RemindCancellationRequestsTask` (`@daily`) reminds the fulfillers once when `currentPeriodEnd` has passed
  and the request is still open — `remindedAt` keeps it to one reminder.

> **Decision: the request tracks work, not permission.** The provider is told at once in both cases, so an
> admin can never "reject" a cancellation. What they confirm is that the teardown is done.

> **Decision: no `requestedBy` on the request.** For a buyer cancellation it is the subscription's own user,
> and an admin action is already in the platform's audit log. A column that is almost always derivable is a
> column that goes stale.

## Refunds

### What is refundable

`Product::$refundPeriodDays` (null = not refundable, the default) is **snapshotted onto the order** at
checkout, like everything else an order needs. Changing a product never changes what an existing buyer was
promised.

`Order::isRefundable()`:

- `Completed` → within `completedAt + refundPeriodDays`
- `Paid` → **always**, with no deadline. The clock starts at delivery, so an order an admin forgot to
  complete is refundable until they do.
- free orders never qualify: there is no money to send back.

### The flow

1. The buyer asks from the order page (`/store/order/{reference}/refund`) with a reason. One
   `RefundRequest` per order.
2. An admin approves or rejects it on the order page, with an optional note that the buyer sees. The
   [dashboard](dashboard.md) lists the pending ones under "Refund requests".
3. Approving calls `RefundService::refund()` → the provider → `PaymentRefunded` → the order becomes
   `Refunded` and `OrderRefundedEvent` fires. Rejecting notifies the buyer and leaves the order alone.

> **Decision: the admin always decides.** There is no automatic refund window, because "14 days, no
> questions asked" is a policy a community sets, not a rule the plugin enforces on their money.

### Refunds that start at the provider

`PaymentRefunded` carries **either** an order reference **or** the provider's payment id, because a Stripe
`charge.refunded` resolves through the payment intent's metadata (one-time), the metadata of the subscription
the first invoice started (a subscription's first payment), or the invoice id (renewal). See
[stripe.md](stripe.md).
`PaymentEventHandler` accepts it for renewal orders too — the only payment event that does.

A refund an admin made in Stripe's own dashboard, and a **lost dispute** (`charge.dispute.closed`), land on
the same event and the same `Order::refund()`. A won dispute changes nothing.

> **Decision: full refunds only.** A partial refund is reported by the provider but deliberately not
> modelled: it would mean a second money column on the order and a status that is neither refunded nor not.
> The handler logs it and leaves the order alone. See [roadmap.md](roadmap.md).

> **Decision: `pay()` refuses a refunded order.** Money arriving late wins over a failure or a
> cancellation, but not over a refund — otherwise a replayed webhook would quietly put a refunded order back
> to `Paid`.

### What a refund undoes

`OrderRefundedEvent` revokes the grants of a **one-time** order (`GrantService::revokeForOrder()`). A
subscription's grants are left alone: they last as long as the subscription does.

**Refunding a payment of a running subscription through the plugin ends that subscription right away**
(`RefundService::refund()`), whether it is the first payment or a renewal, and whether it comes from an
approved request or from the admin's cancel page ("Also refund the last payment", which therefore ends it now
even when the end of the period was picked). Ending it is what takes its grants back and fails its pending
renewals. The buyer's refund form and the admin's approve form both say so first.

The subscription is ended **before** the money goes back: if the provider can't end it, nothing is refunded
(`RefundException::subscriptionNotEnded()`), so a failure never leaves a refunded buyer on a subscription that
keeps charging them.

A refund made at the provider (Stripe's dashboard, a lost dispute) only refunds the order. The provider is
leading: if it keeps the subscription running, so do we, and the subscription keeps its grants.

> **Decision: a refund through the plugin ends the subscription.** Grants used to hang off the refunded
> order, so refunding a subscription's first payment took its role away while the subscription kept
> renewing — the buyer paid every month for nothing — and refunding a renewal took nothing away at all. A
> subscription's grants now follow only the subscription, and a refund we make ends it: the buyer asked for
> their money back, so they must not be charged again. A goodwill refund that keeps the subscription running
> is not offered; it can be done in the provider's dashboard.

## Permissions

`ecommerce.admin.orders.refund` handles refund requests. Cancellation requests are handled under the
existing `ecommerce.admin.subscriptions.manage`.

## Code

- `src/Entity/RefundRequest.php`, `RefundRequestStatus.php`, `CancellationRequest.php`,
  `Order::refund()/isRefundable()`, `Product::$refundPeriodDays`
- `src/Service/RefundService.php`, `src/Service/CancellationRequestService.php`,
  `src/EventSubscriber/CancellationSubscriber.php`, `src/Scheduler/RemindCancellationRequestsTask.php`
- `src/PaymentProvider/PaymentProviderCapability.php`, `InvoiceDocument.php`, `Event/PaymentRefunded.php`
- `src/Controller/OrderController.php` (buyer actions), `src/Admin/Controller/RefundRequestController.php`
- `src/PaymentProvider/Fake/FakeInvoice.php`
- Tests: `RefundTest` (request → approve → grants revoked; reject; a provider-side refund),
  `GrantTest::testRefundingASubscriptionPaymentEndsTheSubscription`, `CancellationTest`
  (buyer cancels a manual subscription, the admin handles it; an instant one needs nobody; the reminder;
  admin cancel + refund in one action), `SubscriptionTest::testBuyerSettlesAPastDueRenewalWithTheProvider`,
  `OrderTest` (refund transitions and the refund window)
