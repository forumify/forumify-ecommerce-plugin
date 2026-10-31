# Subscriptions

A recurring price produces a `Subscription` alongside its order. The provider drives it; we mirror it.

## The entity

| Field | Notes |
| --- | --- |
| `user` | |
| `initialOrder` | One-to-one, `SET NULL`. An order starts at most one subscription |
| `price` | `SET NULL` |
| `providerKey` + `providerReference` | Unique together |
| `status` | `SubscriptionStatus`: active / past_due / cancelled / ended |
| `currentPeriodEnd` | **Paid until** — the end of the last *paid* period |
| `pastDueSince`, `endedAt` | |
| `getGraceEndsAt()` | Computed, see [Past due](#past-due) |

> **Decision: `initialOrder`, not `order`.** `order` is a reserved word in DQL.

> **Decision: `(providerKey, providerReference)` is unique, and events are looked up by both.** A provider
> can only ever touch its own subscriptions.

## Status

```
Active ⇄ PastDue
  │        │
  └───> Cancelled ───> Ended
```

> **Decision: no `cancelAtPeriodEnd` flag.** `cancelled` already means "runs until the period ends, then
> stops". A flag would be a second representation of the same fact, free to disagree with the status.

- `SubscriptionUpdated(cancelAtPeriodEnd: false)` **resumes** a cancelled subscription. No event is
  dispatched, because access never changed.
- `resume()` returns to `PastDue` rather than `Active` while `pastDueSince` is set, so undoing a
  cancellation doesn't hide an unpaid period.
- **`ended` is final.** Nothing brings an ended subscription back.

The state machine lives on the entity — `renew()`, `markPastDue()`, `cancel()`, `resume()`, `end()` — each
returning whether something changed. `PaymentEventHandler` maps `PaymentEvent`s onto them and dispatches the
plugin events after the row lock's transaction commits.

A renewal only counts if it **moves the period forward**, or if the subscription is past due (a retried
payment for the same period).

## Creation

`CheckoutCompleted` for a recurring price creates the subscription.

If the provider sends no `periodEnd`, the first period end is calculated from the order's interval.
`BillingInterval::addTo()` **clamps to the month end** (Jan 31 + 1 month = Feb 28), like payment providers
do, instead of PHP's overflow into March.

A recurring order completed **without** an `externalSubscriptionId` is still completed, but no subscription
is created and an error is logged. The buyer paid; that fact is not thrown away.

## Renewal orders

Every renewal is an `Order` linked to the subscription (`Order.subscription`, `SET NULL`), with the
provider's payment id (Stripe: the invoice id) as its provider reference. `(providerKey, providerReference)`
is unique on orders, which is what makes these events idempotent.

The initial order is linked too: the `Subscription` constructor calls `Order::startSubscription()`. So
`order.subscription` is every payment of a subscription, and `isRenewal()` is "linked, but not its initial
order".

> **Decision: the initial order links to its subscription as well.** It used to be renewals only, which made
> `order.subscription` read as "the subscription this order belongs to" while being `null` for the order
> that started it. Every caller then had to fall back to a lookup by `initialOrder`, and every list of a
> subscription's orders had to add `OR o = :initialOrder`.

| Event | Effect |
| --- | --- |
| `SubscriptionRenewalStarted` | Creates a **pending** renewal order. The subscription **stays active**. |
| `SubscriptionPaymentFailed` | Subscription → past due. With a payment id, also creates the pending order if the start event was missed. The order stays pending while the provider retries. |
| `SubscriptionRenewed` | Completes the order (creating it if needed), and **only then** moves `currentPeriodEnd`. |
| `SubscriptionRenewalVoided` | Fails that pending renewal order (the provider voided it or wrote it off). If it is paid after all, `SubscriptionRenewed` still pays it. |
| `SubscriptionEnded` | Fails the subscription's pending renewal orders. |

> **Decision: a started renewal does not mean past due.** Past due means a payment attempt *failed*, as in
> Stripe. Putting every renewal into past due would fire `SubscriptionPastDueEvent` once per period and lock
> out paying customers.

> **Decision: `currentPeriodEnd` moves only on payment, so it means "paid until".** Access checks should use
> the **status**, not compare the period end with now. `SubscriptionUpdated` carries no period end for the
> same reason.

A payment that still arrives after `SubscriptionEnded` completes its order — money received always wins —
but does not revive the subscription.

A renewal order copies the subscription's price, at the price's amount, and
`SubscriptionRenewedEvent` carries it as `order`. Renewal orders never dispatch
`OrderPaidEvent`/`OrderFailedEvent`, so fulfillment is not triggered twice, and checkout events and
`syncOrder()` ignore them. See [fulfillment.md](fulfillment.md) for the one exception: a manual type's
renewal stays `Paid` for an admin to fulfill.

## Cancellation

`SubscriptionCancellationService` calls `cancelSubscription($subscription, $atPeriodEnd)` on the provider and
applies the returned `PaymentEvent`s immediately, so the admin sees the result at once. The provider's
webhook repeating the same change later is a no-op.

In the admin this is an item in an **Actions** menu on the subscription page (like milhq's quick actions),
opening a page that chooses between the end of the period and right away.

Buyers cancel their own subscriptions from the customer zone, always at period end, through the same
service. A manual fulfillment type's cancellation also opens a `CancellationRequest` for an admin to close
out — see [self-service.md](self-service.md).

The admin's cancel page can **also refund the last payment** in the same action, for the "subscribed to the
wrong price" case. A refund always ends the subscription right away — see [self-service.md](self-service.md).

## Past due

The rule: **access is kept until the end of the unpaid period, or 30 days after the first failed payment,
whichever comes first.**

- `markPastDue()` sets `pastDueSince` (only if not already set). `renew()` clears it.
- `getGraceEndsAt()` computes `min(pastDueSince + 30 days, BillingInterval::addTo(currentPeriodEnd))`.
  Because `currentPeriodEnd` is the end of the last *paid* period, the unpaid period ends one interval later.
- `EndPastDueSubscriptionsTask` (platform scheduler, hourly) finds subscriptions with `pastDueSince` set,
  status `PastDue` or `Cancelled`, and `getGraceEndsAt()` in the past, and cancels them immediately through
  `SubscriptionCancellationService` — exactly as an admin would. The provider's result ends the subscription
  and `SubscriptionEndedEvent` takes access away. A failed provider call is logged and retried next run.

> **Decision: collection is not paused.** forumify.net paused collection at Stripe and scheduled the
> deletion. Here the provider keeps retrying: a successful retry renews the subscription and clears
> `pastDueSince`, and if the provider's own dunning ends the subscription earlier, `SubscriptionEnded`
> simply arrives earlier. Either way works, and we don't have to keep two schedules in sync.

This is also the safety net for a provider that never sends `SubscriptionEnded` — at least for past-due
subscriptions. A cancelled subscription whose provider goes silent without ever having failed a payment is
still not ended by anything. Stripe always sends it; another provider might need a broader sweep.

## Events

`SubscriptionStartedEvent`, `SubscriptionRenewedEvent`, `SubscriptionPastDueEvent`,
`SubscriptionCancelledEvent`, `SubscriptionEndedEvent` — all extending `SubscriptionEvent`, all dispatched
after the lock's transaction commits.

## Admin

A `SubscriptionTable` filtered by status, and a detail page with the details, the Actions menu, and the
subscription's orders below (the `Ecommerce\OrderTable` with a `subscription` prop). Renewal orders carry a
"Renewal" tag in the admin and in the buyer's ledger; a pending renewal explains that the provider charges
the saved payment method. There is no "Try again" for renewals.

## Buyer pages

"My purchases" (`/store/orders`) lists the buyer's subscriptions as cards, past due first. Each card links
to `/store/subscription/{id}`, which shows the plan and **every payment it produced**, and is where the
self-service actions live (the card's `ecommerce_plan_actions` block): cancelling, and on a past-due
renewal a link to the provider's own payment page. A past-due card spells out `getGraceEndsAt()`, so the
buyer sees the date access is lost. See [checkout-and-orders.md](checkout-and-orders.md#buyer-pages) and
[self-service.md](self-service.md).

## Code

- `src/Entity/Subscription.php`, `src/Entity/SubscriptionStatus.php`, `src/Entity/BillingInterval.php`
- `src/Service/SubscriptionCancellationService.php`, `src/Service/PastDueSubscriptionService.php`
- `src/Scheduler/EndPastDueSubscriptionsTask.php`, `src/Exception/SubscriptionException.php`
- `src/Event/Subscription*.php`, `src/Admin/Controller/SubscriptionController.php`
- Tests: `SubscriptionTest` (subscribe → renew → payment fails → renews → admin cancels → ended),
  `PastDueTest`, `SubscriptionTest` (unit), `SubscriptionCancellationServiceTest`, `PaymentEventHandlerTest`
