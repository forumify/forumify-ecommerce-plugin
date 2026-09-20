# Fulfillment

Delivering what was bought, after the money is in.

**Paid is not completed.** Paid means the money arrived; completed means the buyer has what they paid for.
Every use case except the instant ones has a gap between the two.

The use cases that shaped this:

- **forumify cloud**: a subscription per instance. The buyer names the instance, which is provisioned
  asynchronously after payment.
- **forumify marketplace**: one-time and recurring plugin licenses, attached to an OAuth client the buyer picks.
- **Physical goods** (merch): an admin ships them and marks the order as done.
- **Manually delivered digital goods** (e.g. in-game items with no connection to the game server): same, by hand.
- **Roles and badges**: see [grants.md](grants.md) — separate, and stacks with any type.

## The interface

```php
#[AutoconfigureTag('forumify_ecommerce.fulfillment_type')]
interface FulfillmentTypeInterface
{
    public static function getKey(): string;
    public function getLabel(): string;              // a translation key
    public function requiresManualFulfillment(): bool;

    /** Settings stored in Product::$fulfillmentSettings, or null. */
    public function getProductSettingsFormType(): ?string;

    /** Input asked from the buyer at checkout (options: product, user), or null. */
    public function getCheckoutFormType(): ?string;

    /** The checkout form's data → scalars for Order::$fulfillmentData (ids + display labels, never entities). */
    public function normalizeCheckoutData(mixed $data): array;

    /** Twig partial rendered with `order` on the buyer's and admin's order pages and in notifications. */
    public function getDetailsTemplate(): ?string;
}
```

> **Decision: types react to events; the interface only covers what the plugin must render or decide.**
> A type's actual behaviour (provisioning, revoking a license) lives in ordinary event listeners that check
> the order's fulfillment type. The interface is forms, templates, and "does an admin have to act?".

> **Decision: one type per product, not per tier.** Tiers are pricing variants of the same thing. A product
> whose tiers deliver differently is two products.

`FulfillmentTypeRegistry` collects them. An order whose type is **no longer registered** (its plugin was
removed) is treated as manual and shows the raw key — the order stays fulfillable by hand. Unregistered
types on a product also stay selectable in the product form, under their raw key.

## What is stored

**On `Product`:**

- `fulfillmentType` (string, default `instant`, so existing products keep today's behaviour)
- `fulfillmentSettings` (JSON, from the type's product settings form)
- `fulfillmentRole` (nullable `Role`, `SET NULL`) — shown and required only for manual types. Every user
  with this role is notified of new orders to fulfill. If the role is deleted later, orders still show up
  under "Paid", but nobody is notified and a warning is logged.

**On `Order`:**

- `fulfillmentType` — snapshotted from the product
- `fulfillmentData` (JSON) — the buyer's checkout input, as the type normalized it
- `fulfillmentNote` (nullable text) — written by the admin who completed it, shown to the buyer (a tracking
  link, say)
- `completedBy` (nullable `User`, `SET NULL`; null when completed automatically)

> **Decision: everything a type needs is snapshotted on the order.** Editing or deleting a product must never
> break an order that still has to be fulfilled, or a subscription that still has to be revoked.

A renewal order copies `fulfillmentType` and `fulfillmentData` from its subscription's initial order.

**A manual type has a counterpart at the end.** When a subscription of a manually fulfilled product is
cancelled or ends, a `CancellationRequest` asks the same `fulfillmentRole` to close it out — delivery by
hand implies teardown by hand. See [self-service.md](self-service.md).

## Types shipped by the plugin

| Type | Manual | Checkout form | Completes |
| --- | --- | --- | --- |
| `instant` — "Delivered instantly" (default) | no | none | right after payment, by the plugin |
| `manual` — "Delivered by an admin" | yes | one text field, if the product sets a label for it (e.g. "Minecraft username") | when an admin marks it completed |
| `physical` — "Shipped" | yes | shipping address: name, address lines, postal code, city, region, country | when an admin marks it completed |

The physical type prefills the address from the buyer's last physical order, inside its own form type (using
the `user` option) — so the interface needed no extra method for it.

> **Decision: the shipping address is our own form, never delegated to the payment provider.** It then works
> identically for every provider, and for the types forumify.net adds. Shipping costs, rates and stock are
> out of scope: the price includes shipping.

## The flow

```
payment ──> Order::pay() ──> OrderPaidEvent ──┬─ instant type → FulfillmentService::complete()
                                              ├─ manual/physical → stays Paid, admins notified
                                              └─ integration listener → completes when its work is done
                                                          │
                                              FulfillmentService::complete()
                                                          │
                                                 Paid → Completed
                                                 OrderCompletedEvent
```

`OrderPaidEvent` is where fulfillment starts. Free orders go through the same path: `Paid`, and for an
instant type `Completed` right after.

**`FulfillmentService::complete(Order $order, ?User $by = null, ?string $note = null)`**

- moves `Paid → Completed`, saves, and dispatches `OrderCompletedEvent`
- locks the order, like `PaymentEventHandler` does
- is idempotent and returns whether it actually completed the order
- is what integrations call when they're done, and what the admin action calls with the admin and the note

**Renewal rule.** A paid renewal order of a **manual** type stays `Paid`, so a monthly merch box is fulfilled
every period. Any other renewal is completed right away by `PaymentEventHandler`, without
`OrderCompletedEvent` — the subscription simply continues.

## Admin

- **Product form**: a fulfillment type select, then the type's settings form and the fulfillment role
  (required for manual types). Both are toggled by the `fulfillment_form` Stimulus controller, the same way
  `price_form` works. Changing the type only affects new orders.
- **Order list**: the status filter's "Paid" entry *is* the "to fulfill" view. The Orders menu item carries
  the count in its label ("Orders (3)") for users with `orders.fulfill`, because the platform menu has no
  badges.
- **Order page**: the type's details template, and a **"Mark as completed"** action with an optional note for
  the buyer. It is available for every `Paid` order — for non-manual types it is the way to unstick a failed
  integration — and asks for confirmation with a required checkbox.
- **Permission**: `ecommerce.admin.orders.fulfill`.

A failed integration is the integration's own business to report. The order simply stays `Paid` and shows up
under "Paid".

## Buyer

The order page shows `Paid` as "Payment received, being processed", plus the details template and, once
completed, the admin's note. "My orders" shows the status too.

## Types forumify.net will add (not built here)

**`forumify_cloud`**
- Checkout form: the instance name, validated for uniqueness. A real reservation can be made in `OrderPlacedEvent`.
- `OrderPaidEvent` → create the `HostingInstance` (linked to the order, and later its subscription) and
  dispatch `NewInstanceMessage`. The message handler calls `FulfillmentService::complete($order)` once the
  container runs.
- `SubscriptionEndedEvent` → `DeleteInstanceMessage`.
- Deleting an instance cancels its subscription through `SubscriptionCancellationService`, exactly like an
  admin cancellation.
- This replaces pre-creating the instance before checkout, so abandoned checkouts no longer leave orphan
  instances. It also removes `HostingOrderConfirmationNotificationType`.

**`forumify_marketplace`**
- Checkout form: pick one of the user's customers (OAuth clients), normalized to `{customerId, clientName}`.
- `OrderPaidEvent` → create a forumify.net `License(customer, order, ?subscription)` and complete the order.
- `SubscriptionEndedEvent` → remove the license. A one-time license is never revoked.
- Customer pages list licenses and reach their orders and subscriptions through them. The plugin never learns
  what a customer is.
- This removes `ProductConfirmationNotification`.

## Code

- `src/Fulfillment/` (interface, registry, `Type/`, `Form/`)
- `src/Service/FulfillmentService.php`, `src/EventSubscriber/InstantFulfillmentSubscriber.php`
- `src/Twig/FulfillmentExtension.php`, `src/Admin/Form/OrderCompleteType.php`
- `templates/fulfillment/`, `assets/dist/fulfillment_form_controller.js`
- Tests: `FulfillmentTest` (buy a physical product, address form, `Paid`, admin completes with a note, buyer
  sees address and note), `OrderTest`
