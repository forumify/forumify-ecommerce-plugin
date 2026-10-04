# Revenue caps

The plugin is sold in three tiers, and each tier limits how much a community may sell through it. The limit
is enforced in the store itself: checkout is disabled once an order would take the store over it.

## The tiers

| Tier | Monthly revenue |
| --- | --- |
| `hobbyist` | $250 |
| `merchant` | $5,000 |
| `marketplace` | no limit |

The tier is the plugin's **subscription version** on the platform's marketplace (`Plugin.subscriptionVersion`),
read by `RevenueTierResolver`. A store with no subscription, or with a version we don't know, is a
`hobbyist`: the lowest tier is what an unlicensed install gets.

> **Decision: read the subscription version, don't ask `PluginVersionChecker`.** The checker answers "is
> this version installed", and it answers `true` for *every* version in the `dev` and `test` environments so
> that premium features can be developed locally. That bypass is right for a feature gate and wrong here: it
> would silently turn the cap off in every development store and in our own test suite. A revenue limit is a
> business rule, not a feature gate.
>
> To try a tier locally, set `subscription_version` on the plugin's row. A `plugins:refresh` overwrites it
> again with whatever the marketplace says.

## What counts as revenue

`OrderRepository::sumRevenueSince()` sums the orders that are **paid or completed**, by `paidAt`, over the
**trailing three months** — a rolling window from now, not three calendar months. The average that is
checked against the cap is that total divided by three (`RevenueCapService::WINDOW_MONTHS`).

- **Refunded orders are not in it.** The money went back, so it was never revenue. Nothing has to reverse a
  refund separately: the order simply leaves the `paid`/`completed` set.
- **Pending orders are not in it.** An abandoned checkout is not revenue.
- **Renewals are.** A subscription renewal is an order like any other.

> **Decision: measure realized revenue, project the new order on top.** Checkout asks whether
> `(window total + this order) / 3` still fits under the cap. Counting pending orders instead would let
> abandoned checkouts lock a store out of selling. The trade-off is in [roadmap.md](roadmap.md).

## Currencies

The caps are in US dollars, stores sell in their own currency, so the two meet through a conversion table.

`ExchangeRate` (`ecommerce_exchange_rate`) holds one row per supported currency: how much of it one dollar
buys, plus the date the rate is from. `ExchangeRateService` refreshes the table from
[frankfurter.dev](https://frankfurter.dev/) (ECB reference rates) and `UpdateExchangeRatesTask` runs it
daily. A failed refresh logs a warning and changes nothing — last night's rates are close enough for a
monthly limit. The table ships seeded by a migration, so a fresh install can convert before it ever reached
the service.

The **cap** is converted into the store currency, not the revenue into dollars: an admin reads their own
currency everywhere. Revenue that was taken in another currency (orders from before an admin changed the
store currency) is converted through the same table. A currency with no rate falls back to face value and
logs a warning.

## At checkout

`CheckoutService::isBlockedByRevenueCap()` decides, and the checkout page renders the blocked state instead
of the pay button. A POST that arrives anyway is refused with `CheckoutException::revenueCapReached()`.

- **Free prices are never blocked.** They add nothing to the revenue that is capped.
- **Renewals are never blocked.** They never go through `CheckoutService::checkout()`, and they should not:
  a subscriber must not lose what they already pay for because the store outgrew its plan.

## Notifications

The store settings hold a **store role** (`ecommerce.store_role`), the generic "who runs this store"
setting. `StoreNotificationService::notifyStoreManagers()` sends to everyone in it, and any future
store-wide notification can reuse it. Product-level notifications keep using the product's
`fulfillmentRole`.

| Notification | Sent when |
| --- | --- |
| `ecommerce_revenue_cap_reached` | a buyer's checkout was refused by the cap |
| `ecommerce_revenue_cap_warning` | after an order is placed or paid, while the average is at 90% of the cap or more |

`RevenueCapNotifier` sends each kind at most **once every 24 hours**, remembering when it last did in
`ecommerce.revenue_cap.notified.<type>`. Both conditions stay true for days once they are true, and the
point is to tell the store, not to bury it.

`RevenueCapSubscriber` listens on `OrderPlacedEvent` and `OrderPaidEvent` and swallows anything that goes
wrong: a listener on `OrderPlacedEvent` runs inside the checkout, where a throw cancels the order.

## In the admin

The [dashboard](dashboard.md) shows it: the `Ecommerce\Dashboard\PlanUsage` card has the rolling average,
the cap and how much of it is used, a progress bar, the tier and the near/over cap warning;
`Ecommerce\Dashboard\RevenueGraph` plots revenue per month for the last twelve months on the platform's
Chart.js component, right next to it. The revenue cap notifications' "View revenue" link leads there.

## Code

- `src/Revenue/` — `RevenueTier`, `RevenueTierResolver`, `RevenueCapService`, `RevenueUsage`, `RevenueCapNotifier`
- `src/Entity/ExchangeRate.php`, `src/Repository/ExchangeRateRepository.php`, `src/Money/CurrencyConverter.php`
- `src/Service/ExchangeRateService.php`, `src/Scheduler/UpdateExchangeRatesTask.php`
- `src/Service/StoreNotificationService.php`, `src/Notification/AbstractStoreNotificationType.php`,
  `src/Notification/RevenueCap*NotificationType.php`
- `src/EventSubscriber/RevenueCapSubscriber.php`, `src/Admin/Component/Dashboard/{PlanUsage,RevenueGraph}.php`
- Tests: `RevenueCapTest` (checkout blocked, warning, admin figures), `RevenueUsageTest`,
  `RevenueCapServiceTest`, `RevenueCapNotifierTest`, `CurrencyConverterTest`, `ExchangeRateServiceTest`
