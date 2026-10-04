# Notifications

The plugin owns the order and subscription notifications, so an integration never has to write its own
confirmation mail. (forumify.net's `HostingOrderConfirmationNotificationType` and
`ProductConfirmationNotification` go away when it moves onto the plugin.)

All types extend the platform's `AbstractEmailNotificationType` — in-app **and** email — and are sent from
listeners on the plugin's events, which already dispatch after the lock's transaction commits.
`NotificationService` sends mail through messenger, so a mail failure can never fail a webhook.

## To the buyer

| Notification | Trigger | Notes |
| --- | --- | --- |
| **Order completed** | `OrderCompletedEvent` | The main confirmation. For instant types it arrives right after payment and doubles as the receipt. Includes the fulfillment details and the admin's note. |
| **Payment received** | `OrderPaidEvent`, if the order is **still `Paid`** after its listeners ran | Only for types that don't complete right away, so an instant order never gets two mails. Initial orders only. |
| **Order failed** | `OrderFailedEvent` | A real payment failure only — an abandoned checkout maps to `CheckoutCancelled`, not `CheckoutFailed`. |
| **Subscription payment failed** | `SubscriptionPastDueEvent` | States the grace end date. |
| **Subscription cancelled** | `SubscriptionCancelledEvent` | "Runs until {period end}". An **immediate** cancellation sends only "ended". |
| **Subscription ended** | `SubscriptionEndedEvent` | |
| **Order refunded** | `OrderRefundedEvent` | Whoever started the refund, including a lost dispute. |
| **Refund request declined** | `RefundService::reject()` | Carries no note itself; the admin's note is on the order page. |

> **Decision: "payment received" is decided after the listeners ran.** Checking the status at the end of
> `OrderPaidEvent` is what keeps an instant purchase down to one mail, without the notification layer having
> to know which types complete synchronously.

**Renewals send nothing** — the provider's own receipts cover them. The one exception is a manual type's
renewal, which gets "Order completed" once an admin fulfills it.

## To admins

| Notification | Trigger | Recipients |
| --- | --- | --- |
| **New order to fulfill** | `OrderPaidEvent`, or a paid renewal order, of a manual type | every user with the product's `fulfillmentRole` |
| **Cancellation to handle** | a manual type's subscription is cancelled or ends, and again once its paid period runs out | every user with the product's `fulfillmentRole` |
| **Revenue limit reached** | a checkout was refused by the plan's revenue limit | every user with the store role |
| **Near the revenue limit** | an order was placed or paid while the store sits at 90% of its limit | every user with the store role |

The last two are about the **store**, not about one order, so they go to the store role from the settings
through `StoreNotificationService` and are throttled to one a day. See [revenue-caps.md](revenue-caps.md).

A pending **refund request** has no notification: any product can have a refund window, so there is no role
to send it to. The dashboard lists them instead — see [roadmap.md](roadmap.md).

A failed integration (provisioning, say) is the integration's own business to report. The order stays `Paid`
and shows up under "Paid" in the admin.

## Fulfillment details in emails

The order notifications' email templates include the fulfillment type's details template
(`getDetailsTemplate()`), so a type adds its shipping address or instance link to the plugin's mails and
pages without owning a notification of its own.

## Structure

```
AbstractEcommerceNotificationType
  ├ AbstractOrderNotificationType        → templates/emails/notifications/order.html.twig
  │   ├ OrderCompletedNotificationType
  │   ├ OrderPaidNotificationType
  │   ├ OrderFailedNotificationType
  │   └ OrderToFulfillNotificationType
  ├ AbstractSubscriptionNotificationType → templates/emails/notifications/subscription.html.twig
  │   ├ SubscriptionPastDueNotificationType
  │   ├ SubscriptionCancelledNotificationType
  │   └ SubscriptionEndedNotificationType
  └ AbstractStoreNotificationType        → templates/emails/notifications/store.html.twig
      └ AbstractRevenueCapNotificationType
          ├ RevenueCapReachedNotificationType
          └ RevenueCapWarningNotificationType
```

Dispatched by `OrderNotificationSubscriber`, `SubscriptionNotificationSubscriber` and
`RevenueCapSubscriber`.

## Code

- `src/Notification/`, `src/EventSubscriber/*NotificationSubscriber.php`
- `templates/emails/notifications/`
- Tests: `OrderNotificationSubscriberTest`, `SubscriptionNotificationSubscriberTest` ("payment received" is
  not sent for instant orders; an immediate cancellation sends only "ended"), `FulfillmentTest` (a manual
  order notifies the fulfillment role, completing it notifies the buyer)
