# Payment providers

Everything that touches money goes through `PaymentProviderInterface`. Nothing outside
`src/PaymentProvider/<Provider>/` knows which provider is in use.

> **Decision: an abstraction from day one.** forumify.net used its `Stripe` class directly from controllers,
> subscribers and services, which made Stripe impossible to test around and impossible to replace. Here
> Stripe is one implementation of an interface, and the fake provider proves the seam is real.

## The interface

```php
PaymentProviderInterface                    (tag: ecommerce.payment_provider)
  getKey(): string                          'stripe'
  getLabel(): string
  getSettingsFormType(): class-string<FormTypeInterface>
  isConfigured(PaymentProviderConfig): bool
  supports(ProductPrice): bool
  startCheckout(Order, CheckoutUrls): CheckoutRedirect
  syncOrder(Order): list<PaymentEvent>      used by the return page
  handleWebhook(Request): list<PaymentEvent>  verifies the signature, then normalizes
  cancelSubscription(Subscription, bool $atPeriodEnd): list<PaymentEvent>
  getCapabilities(): list<PaymentProviderCapability>   Refund | Invoice | HostedPayment
  refund(Order): list<PaymentEvent>
  getHostedPaymentUrl(Order): ?string       a page to settle an unpaid order and fix the card
  getInvoice(Order): ?InvoiceDocument       ::url(...) to redirect to, or ::file(...) to stream
```

The last four exist for the buyer's self-service pages; a provider that can't do one of them leaves it out
of `getCapabilities()` and nothing offers it. See [self-service.md](self-service.md).

> **Decision: `cancelSubscription()` returns events instead of `void`.**
> `SubscriptionCancellationService` applies them right away, so the admin sees the result immediately. The
> provider's webhook repeating the same change later is a no-op.

## PaymentEvent

Provider-neutral readonly DTOs in `src/PaymentProvider/Event/`. This is the whole vocabulary the rest of the
plugin understands:

| Event | Payload |
| --- | --- |
| `CheckoutCompleted` | orderReference, ?externalCustomerId, ?externalSubscriptionId, ?periodEnd |
| `CheckoutFailed` | orderReference — a real payment failure |
| `CheckoutCancelled` | orderReference — abandoned or expired; never produced by a provider's own vocabulary, see below |
| `SubscriptionRenewalStarted` | externalSubscriptionId, externalPaymentId |
| `SubscriptionRenewed` | externalSubscriptionId, externalPaymentId, periodEnd |
| `SubscriptionPaymentFailed` | externalSubscriptionId, ?externalPaymentId |
| `SubscriptionUpdated` | externalSubscriptionId, cancelAtPeriodEnd |
| `SubscriptionEnded` | externalSubscriptionId |
| `PaymentRefunded` | ?orderReference, ?externalPaymentId, ?amountMinor — a refund, a dashboard refund or a lost dispute |

> **Decision: an expired checkout is cancelled, not failed.** An expired session is an abandoned checkout.
> Mapping it to `CheckoutFailed` would email "your payment failed" to everyone who browsed away, a day later.

## PaymentProviderConfig

`providerKey`, `enabled`, and a `#[SensitiveField]` settings JSON holding the credentials.

> **Decision: credentials in the admin panel, not env vars.** forumify.net used `STRIPE_KEY` and
> `STRIPE_WEBHOOK_SECRET`, which a community admin can't reach. `#[SensitiveField]` keeps them masked in
> audit logs.

`PaymentProviderRegistry` collects the tagged providers and joins them with their config:

- `findEnabled()` — **enabled and configured**. Only these are offered for a new checkout.
- `findConfigured()` — **configured**, enabled or not. These still receive webhooks and syncs, so disabling
  a provider never strands payments that are already in flight.

## PaymentEventHandler

`src/Service/PaymentEventHandler.php` is the **only** place where order and subscription state changes.

It:

- takes the key of the provider that reported the event, and **ignores (and logs) events for orders
  belonging to another provider** (`null` for free orders) — one provider's webhook can never settle
  another's order
- resolves `PaymentRefunded` by order reference *or* by the provider's payment id, and is the **only**
  event applied to renewal orders
- applies each event inside a transaction with the order or subscription row **locked and reloaded**
  (`OrderRepository::withLockedOrder()`, `LockingRepositoryTrait`)
- is idempotent: transitions that already happened are no-ops
- dispatches the plugin's Symfony events **after the commit**, so integrators never see an event twice and
  never see one for a transaction that later rolled back

That combination is what lets the webhook and the return page race safely.

> **Decision: entity methods, not symfony/workflow.** Most transitions record facts reported by the provider,
> which a guard must not veto. Our events also need to fire after the lock's transaction commits and carry
> typed context. Workflow can still be added later for admin-driven processes such as refunds.

## Admin

`PaymentProviderController` lists the registered providers with their enabled/configured status. Each
provider's edit page embeds its own settings form inside a generic `PaymentProviderConfigType`
(`enabled` + the provider's type), and shows the **webhook URL** to paste into the provider's dashboard.

## The fake provider

`src/PaymentProvider/Fake/` — a provider that drives the entire checkout, webhook and subscription flow with
no network calls. `#[WhenNot('prod')]` keeps the services out of production.

It is not only a test double. It runs in dev too, so it doubles as a simulator for the sandbox and for
validating integrations such as forumify.net without a Stripe test account.

- **Simulator page** `/store/fake-payment/{reference}` (owner only): "Pay successfully", "Fail the payment"
  or "Cancel and go back". The first two apply `CheckoutCompleted`/`CheckoutFailed` through
  `PaymentEventHandler` — the same path a real webhook takes.
- **Webhook** accepts a JSON body with an `X-Fake-Signature: valid` header, so flows can be simulated with
  curl or from tests. It takes `renewal_started` (`payment`), `renewed` (optional `payment` and `period_end`,
  otherwise a random payment id and one interval after the current period end), `payment_failed` (optional
  `payment`), `updated` (`cancel_at_period_end`) and `ended`, all with a `subscription` reference.
- Its checkout returns `fake_sub_<order reference>` as the subscription id.
- It is always "configured", so an admin only has to enable it.

## Writing a provider

1. Implement `PaymentProviderInterface` in `src/PaymentProvider/<Name>/` (or in your own plugin — the tag is
   autoconfigured).
2. Add a settings form type for the credentials.
3. Map the provider's webhook payloads onto `PaymentEvent`s in a dedicated mapper class, and verify the
   signature in `handleWebhook()`.
4. Keep every provider-specific id in `providerReferences` on the price and tier, and in `ProviderCustomer`
   for the buyer.

`ProviderCustomer` (user + provider key → external customer id, unique) is how repeat buyers reuse their
customer record at the provider.

## Code

- `src/PaymentProvider/` (interface, registry, `CheckoutUrls`, `CheckoutRedirect`, `Event/`, `Fake/`)
- `src/Service/PaymentEventHandler.php`, `src/Repository/LockingRepositoryTrait.php`
- `src/Entity/PaymentProviderConfig.php`, `src/Entity/ProviderCustomer.php`
- `src/Admin/Controller/PaymentProviderController.php`, `src/Exception/InvalidWebhookException.php`
- Tests: `PaymentEventHandlerTest`, `PaymentProviderRegistryTest`, and `CheckoutTest`/`SubscriptionTest`
  driving the fake provider end to end
