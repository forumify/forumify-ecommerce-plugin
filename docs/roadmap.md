# Roadmap

What is deliberately not built, and what is designed but waiting.

## Out of scope on purpose

**Cart / multiple items per order.** An order is one price and one payment. This is a design choice, not a
gap: it keeps the order snapshot, the subscription link and the fulfillment data unambiguous. A cart would
turn every one of those into a collection.

Also out:

- Taxes (Stripe Tax could become a provider setting later)
- Coupons, refunds, invoices and PDFs
- Multiple currencies per store
- Guest checkout
- Stock, shipping costs, shipping rates, and variants
- Editing a shipping address after checkout
- Several fulfillment types per product, or a type per tier
- Plugin-side reporting of failed integrations
- Revoking one-time grants (needs refunds first)

Everything that belongs only to a marketplace stays in forumify.net: vendors, partners, referrals and
payouts. They build on the plugin's events.

## Designed, not built

### Customer self-service on a subscription

Cancelling is still an admin action (or an integration calling `SubscriptionCancellationService`). The
buyer's zone is now the place for it: `/store/subscription/{id}` exists, and the plan card's
`ecommerce_plan_actions` block is the slot the actions go in — cancel, resume, and whatever a provider can
offer for a failed payment. A past-due plan already tells the buyer the date access ends, but nothing on
the page lets them fix it yet; the provider's own billing portal is the other candidate.

### Moving active subscriptions to a new price

Today, changing a price grandfathers existing subscribers onto the old one (see
[catalog.md](catalog.md)). To move them:

1. An admin action on a replaced price sets a pending price and an effective date on each affected
   `Subscription`, respecting a configurable notice period.
2. Subscribers are notified.
3. A scheduled task calls a new `PaymentProviderInterface::changeSubscriptionPrice()` on the effective date.
   For Stripe, that swaps the subscription item's price with `proration_behavior: none`, so the next renewal
   bills the new amount.
4. The `customer.subscription.updated` webhook maps the Stripe price back through its metadata /
   `providerReferences`, updates `Subscription.price`, and `SubscriptionPriceChangedEvent` is dispatched.

### Ecommerce automation triggers

Triggers for the platform's automations — "order paid", "subscription ended", with a product condition.
Combined with the existing Webhook action, that covers delivery to external systems (game servers, Discord)
with no code at all.

## Known gaps

- **Renaming a product or tier does not update Stripe.** Stripe Product names are set once, at first sync.
- **A cancelled subscription whose provider never sends `SubscriptionEnded` is never ended**, unless it also
  went past due (which the grace-period task sweeps). Stripe always sends it; another provider might need a
  broader sweep. See [subscriptions.md](subscriptions.md).
- **No "resume your purchase" banner** after a guest registers mid-checkout. The session already holds the
  price; flashes can't carry a link, so it needs its own small UI. See
  [checkout-and-orders.md](checkout-and-orders.md).
