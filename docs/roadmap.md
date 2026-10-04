# Roadmap

What is deliberately not built, and what is designed but waiting.

## Out of scope on purpose

**Cart / multiple items per order.** An order is one price and one payment. This is a design choice, not a
gap: it keeps the order snapshot, the subscription link and the fulfillment data unambiguous. A cart would
turn every one of those into a collection.

Also out:

- Taxes (Stripe Tax could become a provider setting later)
- Coupons
- Multiple currencies per store
- Guest checkout
- Stock, shipping costs, shipping rates, and variants
- Editing a shipping address after checkout
- Several fulfillment types per product, or a type per tier
- Plugin-side reporting of failed integrations
- Generating invoices for a provider that issues none (that provider's own problem; the interface streams
  whatever it produces)
- Partial refunds. The provider reports them, the handler logs them and leaves the order alone: modelling
  them means an amount column and a status that is neither refunded nor not
- Re-requesting a refund after a rejection, and a buyer-facing "resume subscription"
- A notification for new refund requests. Any product can have a refund window, so there is no obvious role
  to notify the way `fulfillmentRole` covers fulfillment and cancellations. The dashboard list is the stand-in;
  a store-wide "refunds handled by" role would fix it

Everything that belongs only to a marketplace stays in forumify.net: vendors, partners, referrals and
payouts. They build on the plugin's events.

## Designed, not built

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

### Dashboard widgets

Ideas for the [dashboard](dashboard.md), roughly most useful first. None of them need new tables unless noted.

- **MRR trend and churn.** A daily `#[AsCronTask]` writes one `ecommerce_mrr_snapshot` row (date, MRR,
  active count). That gives an MRR chart, net new MRR per month (new + expansion − churn), and churn rate.
  The only way to get history, because subscription status changes are not stored. *New table.*
- **Platform dashboard card.** A small "Store: 3 orders to process, €1,240 MRR" card on the main admin
  dashboard, linking here. Needs the platform to offer a hook (a Twig block or tagged widget) first.
- **Subscriptions ending soon.** `Cancelled` subscriptions whose period ends in the next 14 days, and
  past-due ones whose grace runs out soon (`getGraceEndsAt()`): a list for reaching out before the
  customer is gone.
- **Recent activity feed.** The last N paid orders, refunds and cancellations as one timeline. Gives the
  page a pulse and replaces checking the orders list for "anything new?".
- **Average order value and new vs returning buyers** for the selected period, next to the product breakdown.
- **Abandoned checkouts.** Pending orders older than a day, per product, and a checkout → paid conversion
  rate. Shows a price or provider problem early. Pending orders are already stored, so it is only a query.
- **Refund rate per product.** Refunded vs paid in the period; a product with a high rate is worth a look.
- **Payment provider health.** Per provider: payments in the last 7 days, failed renewals, the time of the
  last webhook. A silent webhook is the most expensive failure there is, and nothing shows it today.
- **Exchange rate freshness.** A warning when the newest `ExchangeRate.date` is more than a few days old,
  because the revenue cap then converts with stale rates.
- **Store health checklist.** For a new store: no payment provider configured, no store role set, a product
  with a manual fulfillment type and no fulfillment role, no products published. Disappears once all done.
- **CSV export** of the product breakdown and the revenue per month, for bookkeeping.
- **Lazy-loaded widgets.** Render the cards with Live components' deferred loading so the page shows at once
  and each card fills in. Worth it once the product breakdown runs over a year of a busy store.

## Known gaps

- **A burst of checkouts can cross the revenue cap together.** The cap counts paid orders and projects the
  one being placed, so several people who all reach checkout while the store is just under its limit can
  each be allowed and then all pay. The store lands over its cap and is blocked from the next sale, which
  is the behaviour we want for a monthly average; counting pending orders instead would let abandoned
  checkouts lock a store out. See [revenue-caps.md](revenue-caps.md).
- **Renaming a product or tier does not update Stripe.** Stripe Product names are set once, at first sync.
- **A cancelled subscription whose provider never sends `SubscriptionEnded` is never ended**, unless it also
  went past due (which the grace-period task sweeps). Stripe always sends it; another provider might need a
  broader sweep. See [subscriptions.md](subscriptions.md).
- **No "resume your purchase" banner** after a guest registers mid-checkout. The session already holds the
  price; flashes can't carry a link, so it needs its own small UI. See
  [checkout-and-orders.md](checkout-and-orders.md).
