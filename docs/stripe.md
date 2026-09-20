# Stripe provider

The concrete implementation of [payment-providers.md](payment-providers.md), in
`src/PaymentProvider/Stripe/`. Uses `stripe/stripe-php` ^21.

| Class | Does |
| --- | --- |
| `StripePaymentProvider` | The `PaymentProviderInterface` implementation |
| `StripeSettings` | Reads the credentials out of the config's settings array |
| `StripeSettingsType` | The admin settings form |
| `StripeClientFactory` | Builds the `StripeClient`, so unit tests can mock it |
| `StripeCatalog` | Price, tier-product and customer sync |
| `StripeEventMapper` | Stripe event or object → `PaymentEvent`s; also used by `syncOrder()` and `cancelSubscription()` |

## Settings

Secret key and webhook secret. Password fields that are **never rendered back**: leaving a field empty keeps
the saved value, and the placeholder shows a masked hint of it. Keys are validated by prefix
(`sk_`/`rk_` + `test_`/`live_`, and `whsec_`). The webhook secret's help text lists the events to select in
Stripe, and the edit page shows the webhook URL to paste there.

> **Decision: no publishable key.** Hosted Checkout never uses it. It can come back if embedded Checkout does.

**Test-mode keys keep their references under `stripe_test`** (live keys use `stripe`), both in
`providerReferences` and in `ProviderCustomer.providerKey`. Switching from test to live keys then creates
fresh products, prices and customers instead of failing on test ids.

## Catalog mapping

| Ours | Stripe |
| --- | --- |
| `ProductTier` | one Stripe **Product**, named "Product – Tier" (or just "Product" when the tier label equals the title) |
| `ProductPrice` | one Stripe **Price** |

Both carry metadata (`ecommerce_product_id`, `ecommerce_tier_key`, `ecommerce_price_id`), and the Stripe ids
are kept in `providerReferences` on the tier and the price.

**Stripe never needs to understand tiers.** Our `Order` and `Subscription` point at our price, and the price
knows its tier.

### Lazy sync

> **Decision: the local price is the source of truth, and Stripe is written to only at checkout.**
> forumify.net pushed the product to Stripe on every admin save and let Stripe's amount overwrite the local
> one — so a Stripe outage broke the admin panel, and the catalog could silently drift. Here an admin save
> never calls Stripe. A missing Stripe price is created when a checkout starts.

**"Stale" prices can't happen**: an order locks its price before `startCheckout()` runs, so a synced price
can never change afterwards.

When a price that **replaced** another is synced for the first time, the predecessor's Stripe Price is
archived (`active=false`) — walking back through unsynced predecessors to the nearest synced one.
Subscriptions already on it keep working. A failure to archive is logged and does not block the checkout.

Stripe Product names are set once. **Renaming a product or tier does not update Stripe yet.**

## Checkout

A Checkout Session in `payment` or `subscription` mode depending on the price type, with
`client_reference_id` = the order reference, plus metadata.

The customer is created **before** the session (email, display name, forumify user id in metadata) and
reused through `ProviderCustomer`. That makes `CheckoutCompleted.externalCustomerId` only informational.

`supports()` accepts every price: all store currencies use the same minor units at Stripe, and Stripe rejects
anything else when the session is created — which already fails the order cleanly.

## Webhook events

Handled: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
`checkout.session.async_payment_failed`, `checkout.session.expired`, `invoice.created`, `invoice.paid`,
`invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`.

Mapping rules:

- A session that is `complete` and not `unpaid` → `CheckoutCompleted` (a subscription's period end is read
  from Stripe).
- `expired` → `CheckoutCancelled` (abandoned, not failed). `async_payment_failed` → `CheckoutFailed`.
- Sessions without `client_reference_id` are ignored — they aren't ours.
- Only `subscription_cycle` invoices count as renewals. A failed **first** invoice gives
  `SubscriptionPaymentFailed` without a payment id, so no renewal order is created for it.
- `cancel_at` counts as a cancellation, like `cancel_at_period_end`.

### API version drift

`stripe/stripe-php` ^21 targets API version `2026-08-26.dahlia`, but webhook endpoints registered on older
versions still send the old shapes. The mapper handles both:

- the invoice's subscription is read from `parent.subscription_details.subscription`, falling back to the old
  top-level `subscription`
- period ends come from the invoice lines or the subscription items, falling back to the old
  `current_period_end`

## Verified manually

In the sandbox against a Stripe test-mode account with
`stripe listen --forward-to https://localhost:8000/store/webhook/stripe` (webhooks on API version
`2024-04-10`, the CLI default):

- a one-time purchase
- a subscription purchase, with `invoice.paid` arriving **before** `checkout.session.completed`
- on a Stripe test clock: a renewal (pending renewal order → completed, period end +1 month) and a declined
  renewal (past due, renewal order stays pending)
- an admin cancellation at period end (Stripe `cancel_at_period_end`; the echo webhook is a no-op), then one
  right away (ended, pending renewal order failed)

Every webhook returned 204, and one Stripe customer was reused for all purchases.

## Code

- `src/PaymentProvider/Stripe/`
- Tests: `StripeCatalogTest`, `StripePaymentProviderTest`, `StripeTestTrait` (mocked `StripeClient`: price
  sync create/reuse/replace, session parameters for one-time vs subscription, the full event mapping, bad
  signature rejection); `StripeTest` (a correctly signed fixture payload completes an order)
