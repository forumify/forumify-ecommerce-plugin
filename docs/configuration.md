# Configuration

Plugin wiring, permissions, routing, store settings and money handling. This is the base every other
feature builds on.

## Permissions

Declared in `ForumifyEcommercePlugin::getPermissions()`. The plugin is named "Ecommerce", so the strings
are `ecommerce.<path>`:

```php
'admin' => [
    'view',
    'configuration' => ['manage'],
    'payment_providers' => ['manage'],
    'categories' => ['view', 'manage'],
    'products' => ['view', 'manage'],
    'orders' => ['view', 'fulfill', 'refund'],
    'subscriptions' => ['view', 'manage'],
],
```

Renaming the plugin would change every permission string, so don't.

## Routing

`config/routes.yaml` imports three route sets:

| Import | Resource | Prefix | Name prefix |
| --- | --- | --- | --- |
| `ecommerce_admin` | `src/Admin/Controller` | `admin/ecommerce/` | `ecommerce_admin_` |
| `ecommerce` | `src/Controller` | `store` (`trailing_slash_on_root: false`) | `ecommerce_` |
| `ecommerce_fake_payment` | `src/PaymentProvider/Fake` | `store` | `ecommerce_` |

The fake provider's import carries `condition: "'%kernel.environment%' != 'prod'"`. Routes don't follow
`#[WhenNot]`, so the route still exists in prod but can never match there.

`/store/{slug}` is a catch-all product route. Any new single-segment storefront route (`/store/checkout`,
`/store/orders`) needs `priority: 10` to win over it.

The admin menu is built by `EcommerceMenuBuilder` as a top-level "Ecommerce" section. The plugin's
`settingsRoute` is `ecommerce_admin_configuration`. Three menu items carry a count of what is waiting —
orders to fulfill, refund requests, cancellations to handle — because the platform's menu has no badges.

## Store settings

Stored through the platform's `SettingRepository` under the `ecommerce.*` prefix and read through
`StoreSettings`. The admin page is `ConfigurationController` + `ConfigurationType`.

Two settings:

- The **store currency** (default USD). It is snapshotted onto every price, so changing it later never
  rewrites what was already sold. The list is limited to 21 major currencies
  (`StoreSettings::SUPPORTED_CURRENCIES`) and the field uses autocomplete.
- The **store role**: who runs this store. Everyone in it is notified about the store itself rather than
  about one order — today the revenue limit, see [revenue-caps.md](revenue-caps.md) — through
  `StoreNotificationService`. Notifications about a single order use the product's `fulfillmentRole`
  instead.

> **Decision: no "store enabled" setting.** Admins control storefront visibility through the Menu Builder,
> which already has permissions. A separate toggle would be a second, competing answer to the same question.

## Money

Money uses [brick/money](https://github.com/brick/money). Never floats.

**Storage.** Plain columns, no Doctrine embeddable and no custom type. Every amount is an `int` in minor
units (`amountMinor`), and each entity has one `currency` column shared by all of its amounts. A getter
builds the object:

```php
public int $amountMinor;
public string $currency;

public function getAmount(): Money
{
    return Money::ofMinor($this->amountMinor, $this->currency);
}
```

**Calculations.** Combined amounts (totals, and tax when it arrives) are calculated exactly with
`RationalMoney` and rounded **once, at the result** — never per line. This is what e-invoicing
(EN 16931 / Peppol) requires.

**Sub-cent unit prices** would need `DECIMAL` storage, but only once quantities above 1 or tax exist.
Neither does, so minor units are enough.

**Between currencies.** A store sells in one currency, so nothing in the catalog converts. The only
conversion is the revenue limit, which is in dollars: `CurrencyConverter` and the daily
`ecommerce_exchange_rate` table do that, see [revenue-caps.md](revenue-caps.md).

> **Decision: brick/money over a home-made value object.** The first pass had its own `Money` class. It was
> replaced because rounding, currency metadata and exact rational arithmetic are exactly the parts that are
> easy to get subtly wrong.

### Formatting

- `MoneyFormatter` → the `ecommerce_money` Twig filter. Locale aware, and respects each currency's decimal count.
- `PriceFormatter` → `ecommerce_price` and `ecommerce_starting_price`. Renders "Free" for 0, recurring prices
  with their interval, and "From X" for a tier or product with several prices. It accepts an `Order` or a
  `ProductPrice`.

Both are in `src/Money/`, exposed by `src/Twig/MoneyExtension.php`.

### Form input

`MoneyAmountType` + `MinorUnitsTransformer` (`src/Form/`) take a major-unit amount from the admin and store
minor units.

## Testing base

`EcommerceWebTestCase` (`tests/Tests/Application/`) gives a client logged in as a super-admin, built with
factories. Application tests extend it.

## Code

- `src/ForumifyEcommercePlugin.php`, `config/routes.yaml`, `config/services.yaml`
- `src/Service/StoreSettings.php`, `src/Admin/Controller/ConfigurationController.php`
- `src/Money/`, `src/Form/`, `src/Twig/MoneyExtension.php`
- Tests: `ConfigurationTest`, `PluginBootTest`, `MoneyFormatterTest`, `PriceFormatterTest`,
  `StoreSettingsTest`, `MinorUnitsTransformerTest`
