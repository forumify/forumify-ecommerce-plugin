# Catalog

Categories, products, tiers and prices, plus the admin CRUD that manages them. All tables are prefixed
`ecommerce_`.

## Domain model

```
ProductCategory ──M:N── Product ──1:N── ProductTier ──1:N── ProductPrice
                           └──1:N── ProductImage
```

### ProductCategory
`title`, `slug`, plain-text `description`, `position`.

The description is shown above the products when that category is selected on the storefront.

### Product
`title`, `key`, `slug`, short description, rich-text `description`, cover `image`, `ProductStatus`
(draft / published / archived), categories (M:N), `position`, tiers, gallery images, plus the fulfillment
and grant settings documented in [fulfillment.md](fulfillment.md) and [grants.md](grants.md).

`key` is globally unique and stable (e.g. `forumify/forumify-milhq-plugin`). Together with a tier key it is
how an integration identifies what was sold, independently of ids and slugs. It locks once the product has a
locked price, like tier keys.

> **Decision: `title`, not `name`.** It lets `Product` reuse the platform's `SluggableEntityTrait`, and it
> matches the order snapshot's "product title".

> **Decision: no SKU or GTIN.** There are no physical goods with stock, and an SKU would belong on the tier
> anyway. It can be added there when it earns its place.

Products are **never hard deleted once they have orders** — they are archived. The guard uses
`Product::hasLockedPrices()`, because placing an order always locks its price. Deleting shows an error that
points to archiving.

### ProductTier
`product`, `key` (unique per product: `free`, `basic`, `premium`), `label`, description / feature list,
`highlighted`, `position`, prices, `providerReferences` (JSON, e.g. the Stripe Product id).

`highlighted` renders as "Recommended" in the tier comparison, and is labelled that way in the admin.

A tier owns one or more prices — monthly, yearly at a discount, lifetime. **A tier always has at least one
price**: the "Add tier" form includes its first price, and a tier's last price can't be deleted (deactivate
it instead).

> **Decision: a free tier is a tier with a price of 0, never a tier without prices.** Every order points at
> exactly one price, so a priceless tier would be unbuyable by construction.

### ProductPrice
`tier`, `amountMinor` + `currency`, `PriceType` (one_time / recurring), `BillingInterval`
(day / week / month / year) + interval count for recurring prices, `active`, `locked`, `replacedBy`,
`providerReferences` (JSON, e.g. `{"stripe": "price_…"}`).

Validation: a recurring price needs an interval, a one-time price must not have one, and the amount is ≥ 0.

**A free price is always one-time.** Nothing would ever renew it, and there is no provider to cancel it with.
`ProductPriceType` clears the type and interval on `SUBMIT` when the amount is 0, and the `price_form`
Stimulus controller hides those fields while the amount is 0 (restoring the previous type when the amount
changes again).

## Price locking and replacement

Once an order references a price, its amount, currency, type, interval and tier are **locked** — the same way
Stripe treats prices as immutable. That keeps every order and subscription pointing at exactly what was sold.

- Checkout calls `ProductPrice::lock()` when an order first references the price.
- A locked price can't be edited or deleted; its fields are shown disabled in the admin.
- A locked price **can** still be activated and deactivated.
- A tier with a locked price can't be deleted, and its `key` can't be changed.

To change a price (inflation, a new plan), the admin uses **"Change price"** (`PriceChangeService`):

1. A new `ProductPrice` is created in the same tier.
2. The old one is deactivated and its `replacedBy` is set to the new one.
3. The storefront only offers active prices. Past orders keep their snapshot.
4. The new price reaches the provider through lazy sync on its first checkout (see [stripe.md](stripe.md)).

Existing subscriptions stay on the old price — grandfathered. Moving them is designed but not built; see
[roadmap.md](roadmap.md).

> **Decision: an explicit `locked` column, not a lookup.** Checking "does an order reference this price?" on
> every edit is a query that grows with order volume, and the answer must not change if order history is
> ever pruned. A flag set once at checkout says the same thing permanently.

### ProductImage

`product`, `image`, `position`. Managed on a "Gallery" tab of the product edit page: multi-file upload, a
sortable table, and deleting a row deletes the file.

`Product::$image` stays the **cover** image (the catalog tile), and `Product::getGallery()` is the cover followed
by the gallery images.

## Admin

`ProductCategoryController` and `ProductController` extend the platform's `AbstractCrudController`.

Tiers, prices and gallery images are edited on **tabs of the product edit page**, as extra routes on the
product CRUD controller (the same shape as milhq's award tiers):

- **Tiers** tab → a reorderable `ProductTierTable`.
- A tier's edit page has a **Prices** tab → a reorderable `ProductPriceTable`, with the "Change price" action.
- **Gallery** tab → `ProductImageTable`.

All catalog entities implement `AuditableEntityInterface`.

Uploads use `Forumify\Core\Form\UploadType` with the plugin's own flysystem storage and asset package
(`config/packages/flysystem.yaml`, `framework.yaml`), and LiipImagine filters for the rendered sizes
(see [storefront.md](storefront.md)).

## Code

- `src/Entity/Product*.php`, `src/Entity/PriceType.php`, `src/Entity/BillingInterval.php`
- `src/Admin/Controller/Product*.php`, `src/Admin/Form/`, `src/Admin/Component/Product*Table.php`
- `src/Service/PriceChangeService.php`, `src/Exception/PriceLockedException.php`
- `assets/dist/price_form_controller.js`
- Tests: `CatalogAdminTest`, `ProductTest`, `ProductTierTest`, `ProductPriceTest`, `PriceChangeServiceTest`
- Factories: `ProductFactory`, `ProductCategoryFactory`, `ProductTierFactory`, `ProductPriceFactory`,
  `ProductImageFactory`
