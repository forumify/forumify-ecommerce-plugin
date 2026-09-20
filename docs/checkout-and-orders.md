# Checkout and orders

Turning a chosen price into an `Order`, getting it paid, and showing it to the buyer and the admin.

## The Order entity

An order is **exactly one price**. There is no cart, by design — see [roadmap.md](roadmap.md).
An order is also **one payment**: either the checkout that starts a purchase, or one renewal of a
subscription (`Order.subscription`).

| Field | Notes |
| --- | --- |
| `reference` | Public, non-sequential |
| `user`, `product`, `price`, `subscription` | All `SET NULL`; `user` is nullable too |
| `status` | `OrderStatus`: pending / paid / completed / failed / cancelled |
| `providerKey`, `providerReference` | The provider and its id (checkout session, or invoice for a renewal). `(providerKey, providerReference)` is unique |
| snapshot | product key + title, tier key + label, amount + currency, price type, interval |
| `fulfillmentType`, `fulfillmentData`, `fulfillmentNote`, `completedBy` | See [fulfillment.md](fulfillment.md) |
| timestamps | `createdAt`, `paidAt`, `completedAt` |

> **Decision: snapshot everything, link with `SET NULL`.** Deleting or editing catalog data must never damage
> order history. The links are a convenience for the admin UI; the snapshot is the record.

### Status transitions

```
Pending ──pay()──> Paid ──complete()──> Completed
   │                 ▲
   ├──fail()────> Failed ──┤
   └──cancel()─> Cancelled ┘
```

- **`pay()` works from every state except `Paid`/`Completed`.** Money that arrives late always wins, even
  after a failure or a cancellation. `paidAt` is set here.
- `complete()` only goes `Paid → Completed`, and sets `completedAt`.
- `fail()` and `cancel()` only work from `Pending`.
- Every transition returns whether anything actually changed, which is what makes the webhook and the return
  page safe to race.

> **Decision: paid is not completed.** Paid means the money is in; completed means the buyer has what they
> paid for. Every non-instant fulfillment type has a gap between the two.

## The checkout flow

1. **Pick a price.** The tier form on the product page does a plain GET to `/store/checkout?price=<id>`,
   which redirects to the canonical `/store/checkout/{price}`. Login redirects back to that URL.
2. **Log in.** Checkout requires a login. A guest is sent to log in and returns through Symfony's saved
   target path. The chosen price is also kept in the session, so someone who registers first (and verifies
   their email) can be offered to resume.
3. **Confirm.** The checkout page shows what is being bought, the fulfillment type's checkout form (as a
   `fulfillment` child — including for free prices), and the provider choice if there is more than one. It
   submits with a CSRF-protected POST.
4. **`CheckoutService::checkout()`** creates a pending `Order` with the snapshot and the normalized
   fulfillment data, locks the price, and dispatches `OrderPlacedEvent`. A listener may throw
   `CheckoutException` here to abort (e.g. to reserve a name).
   - **Amount 0** → the order is paid immediately through `PaymentEventHandler`, with no provider key.
   - **Otherwise** → the provider is chosen: automatically if exactly one enabled provider `supports()` the
     price, otherwise from the buyer's choice. `startCheckout()` returns a redirect.
5. **Return.** `/store/order/{reference}` (owner only) calls `syncOrder()` once and shows the status.
6. **Webhook.** `/store/webhook/{providerKey}` → `handleWebhook()` → `PaymentEventHandler`.

Steps 5 and 6 race deliberately; see [payment-providers.md](payment-providers.md).

### Cancelling

The provider's cancel URL is `/store/order/{reference}/cancel`, which applies a `CheckoutCancelled` event
(never produced by a provider) and lands on the order page. There is no `OrderCancelledEvent` — nothing
downstream cares that a buyer changed their mind.

### Errors

- `startCheckout()` throws → the order is failed, the exception is logged, the buyer sees a friendly error.
- `syncOrder()` throws → logged, and the page just shows the current status.

## Events

| Event | When |
| --- | --- |
| `OrderPlacedEvent` | The order is saved, before the redirect to the provider. A listener may throw `CheckoutException`. |
| `OrderPaidEvent` | An initial order is paid. **This is where fulfillment starts.** Carries the subscription it started, if any. |
| `OrderCompletedEvent` | `FulfillmentService::complete()` moved an order from `Paid` to `Completed`. Informational. |
| `OrderFailedEvent` | A real payment failure. |

**Renewal orders never dispatch `OrderPaidEvent` or `OrderFailedEvent`**, so fulfillment is not triggered
twice. They have `SubscriptionRenewedEvent` instead. The one exception is `OrderCompletedEvent`, which does
fire for renewals of manual types — an admin fulfilled something, and the buyer should hear about it.

## Buyer pages

- **`/store/order/{reference}`** — an `Ecommerce\OrderStatus` Live component that polls every 3 seconds
  while the order is pending. `Paid` reads as "Payment received, being processed". Shows the fulfillment
  type's details template and the admin's note once completed.
- **`/store/orders`** — a `MyOrderList` Live list of the user's orders and active subscriptions, linked from
  the store header. Renewal orders carry a "Renewal" tag.

Both are single-segment routes, so they use `priority: 10` to beat `/store/{slug}`.

> **Not built: the "resume your purchase" banner.** Flashes are plain translated text and can't carry a
> link, so offering to resume a purchase after registration needs its own small UI. The session already
> holds the price.

## Admin

- **Order list** — `OrderTable` with status and product filters as Live props above the table. The status
  filter's "Paid" entry is the "to fulfill" view; the Orders menu item carries the count in its label
  ("Orders (3)") for users with `orders.fulfill`, because the platform menu has no badges.
- **Order page** — read-only details plus the provider reference, the fulfillment details template, a
  "Mark as completed" action, and a link to the subscription the order started or renewed.

Dates are shown in the viewer's timezone through the platform's `TimezoneResolver`, like the rest of the site.

## Code

- `src/Entity/Order.php`, `src/Entity/OrderStatus.php`, `src/Repository/OrderRepository.php`
- `src/Service/CheckoutService.php`, `src/Controller/CheckoutController.php`,
  `src/Controller/OrderController.php`, `src/Controller/WebhookController.php`
- `src/Component/OrderStatus.php`, `src/Component/MyOrderList.php`
- `src/Admin/Controller/OrderController.php`, `src/Admin/Component/OrderTable.php`
- `src/Event/Order*.php`, `src/Exception/CheckoutException.php`
- Tests: `CheckoutTest` (paid checkout, return-page sync, abandon then pay, guest redirect, free tier),
  `OrdersAdminTest`, `CheckoutServiceTest`, `OrderTest`
