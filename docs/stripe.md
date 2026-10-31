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
(`sk_`/`rk_` + `test_`/`live_`, and `whsec_`). The webhook secret's help text names the four event groups to
select in Stripe, and the edit page shows the webhook URL to paste there.

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

A `payment` session also sets `invoice_creation.enabled`, because Stripe issues no invoice for a one-time
payment otherwise and the buyer's "Download invoice" would have nothing to fetch. Subscriptions always
invoice.

## Self-service capabilities

Stripe declares all three of [self-service.md](self-service.md)'s capabilities, plus `Settlement`:

- **Refund** — the payment intent is resolved from the order's provider reference and refunded in full. A
  one-time checkout session has its own `payment_intent`. A **subscription** session has none (Stripe only
  sets it in `payment` mode): its first payment is paid through the session's `invoice`, and a renewal through
  its own invoice. An invoice's payment intent is looked up through the invoice payments API
  (`invoicePayments->all(['invoice' => …, 'status' => 'paid'])`), because the invoice's `payment_intent` field
  is gone since API version 2025-03-31 and its `payments` list is not returned by default.
- **Invoice** — the invoice's `invoice_pdf`, returned as a URL the buyer is redirected to.
- **HostedPayment** — a renewal's `hosted_invoice_url`, which is where the buyer pays a failed renewal and
  replaces their card. (The Billing Portal is the alternative, but it has to be configured in the Stripe
  dashboard first; the hosted invoice page does not.)

- **Settlement** — the same payment intent as a refund, retrieved with
  `latest_charge.balance_transaction` expanded. The balance transaction's `amount` and `net` are in the
  account's settlement currency, which can differ from the order's. Null while the charge has no balance
  transaction yet.

## Webhook events

The admin is told to subscribe the endpoint to **all `checkout`, `invoice`, `customer` and `charge`
events**. Those four resources cover everything the mapper acts on:

| Acted on | Ignored, but delivered |
| --- | --- |
| `checkout.session.completed`, `…async_payment_succeeded`, `…async_payment_failed`, `…expired` | — |
| `invoice.created`, `invoice.paid`, `invoice.payment_failed`, `invoice.voided`, `invoice.marked_uncollectible` | every other `invoice.*` |
| `customer.subscription.updated`, `customer.subscription.deleted` | every other `customer.*` |
| `charge.refunded`, `charge.dispute.closed` (lost only) | every other `charge.*` |

> **Decision: ask for four event groups, not thirteen event names.** Stripe's endpoint form lets you tick a
> whole resource in one click, and a list of thirteen names is thirteen chances to mistype one — a missed
> `invoice.paid` silently breaks renewals, and the admin has no way to notice until a subscription fails
> to renew. Over-subscribing costs only ignored deliveries: `StripeEventMapper` dispatches on the event's
> object type and then its name, and **every** match has a `default => []`, so an unrecognised event maps
> to no `PaymentEvent` and the webhook still answers 204. Deliberately, there is no longer a
> `HANDLED_EVENTS` constant: nothing rendered it once the help text stopped listing names, and a hand-kept
> copy of the match arms is a copy that goes stale.

Mapping rules:

- A session that is `complete` and not `unpaid` → `CheckoutCompleted` (a subscription's period end is read
  from Stripe).
- `expired` → `CheckoutCancelled` (abandoned, not failed). `async_payment_failed` → `CheckoutFailed`.
- Sessions without `client_reference_id` are ignored — they aren't ours.
- Only `subscription_cycle` invoices count as renewals. A failed **first** invoice gives
  `SubscriptionPaymentFailed` without a payment id, so no renewal order is created for it.
- `cancel_at` counts as a cancellation, like `cancel_at_period_end`.
- A renewal invoice that is `voided` or `marked_uncollectible` → `SubscriptionRenewalVoided`, which fails its
  pending order. Stripe can still collect an uncollectible invoice later; its `invoice.paid` then pays it.
- `charge.refunded` → `PaymentRefunded`. A **lost** `charge.dispute.closed` maps to the same event; a won one
  to nothing. The order is found by, in turn:
  1. `ecommerce_order_reference` in the charge's or its payment intent's metadata — a one-time payment
     (`payment_intent_data.metadata`)
  2. otherwise the payment's invoice: named on the charge by webhooks before API version 2025-03-31, found
     through the invoice payments API after it (`invoicePayments->all(['payment' => …])`)
  3. a `subscription_create` invoice → the order reference in `parent.subscription_details.metadata`, the
     snapshot of the metadata our checkout put on the subscription (`subscription_data.metadata`): the
     subscription's first order, whose provider reference is the checkout session, not the invoice
  4. any other invoice → its id, which is a renewal order's provider reference

> **Decision: subscription payments are found through their invoice.** Stripe copies
> `subscription_data.metadata` onto the subscription, never onto the payment intents of its invoices, and
> since 2025-03-31 a charge no longer names its invoice. An earlier version read only `charge.invoice` and the
> payment intent's metadata: it passed the tests (which mocked the old charge) and the manual run (the Stripe
> CLI forwards on `2024-04-10`), but on a webhook endpoint created today every refund or lost dispute of a
> subscription payment mapped to no order and was dropped with a 204.

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

Refunds, on 2026-10-04, against the test API on `2026-08-26.dahlia`:

- a scratch script refunded a one-time payment intent, a subscription's first invoice and a renewal invoice
  (on a test clock) and ran the real `charge.refunded` charges through `StripeEventMapper`: each resolved to
  its order (by reference, by the subscription's metadata, by the renewal's invoice id)
- in the sandbox with `stripe listen`: a subscription checkout, then the admin's cancel page with "at the end
  of the period" and "Also refund the last payment" — the subscription was set to cancel at period end, the
  refund then ended it right away (`customer.subscription.deleted`), the payment was refunded through the
  session's first invoice, its grants were revoked, and the echoed `charge.refunded` was a no-op

## Code

- `src/PaymentProvider/Stripe/`
- Tests: `StripeCatalogTest`, `StripePaymentProviderTest`, `StripeTestTrait` (mocked `StripeClient`: price
  sync create/reuse/replace, session parameters for one-time vs subscription, the full event mapping including
  refunds on both API shapes, refunding each kind of order, bad signature rejection); `StripeTest` (a correctly signed fixture payload completes an order)
