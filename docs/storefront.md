# Storefront

The public `/store` pages: a catalog and a product page. Both are public — visibility is controlled by the
Menu Builder, not by a setting.

## Pages

### `/store` — catalog

`StoreController` renders the `Ecommerce\ProductList` Live list (`AbstractDoctrineList`), URL-bound as
`?category=<slug>&q=<text>`.

- Only **published** products are listed, and only categories that contain one.
- Search matches the title and the short description.
- A selected category shows its description above the products.

### `/store/{slug}` — product page

An image gallery, the description, a tier comparison, and a price picker per tier that leads to checkout.
A draft product returns 404.

- With **one** purchasable tier, its buy box is in the page header.
- With **several**, the header shows the starting price and links down to the comparison.

Each tier card is a `<form>` with a `price` radio group, submitting a GET to `/store/checkout?price=<id>`.
See [checkout-and-orders.md](checkout-and-orders.md).

This route is a catch-all, so new single-segment storefront routes need `priority: 10`.

## Presentation logic

Presentation logic lives **on the entities**, not in the templates:

- `ProductTier::getActivePrices()`, `::getCheapestPrice()`
- `Product::getPurchasableTiers()`, `::getActivePrices()`, `::getCheapestPrice()`, `::getGallery()`
- `ProductPrice::cheapest()`

…and in `PriceFormatter` (`format()`, `formatStartingPrice()`), exposed as the `ecommerce_price` and
`ecommerce_starting_price` Twig filters.

## JavaScript

The `product` Stimulus controller (`assets/dist/product_controller.js`) drives:

- the gallery: thumbnails, prev/next, arrow keys, and a full-size `<dialog>`
- each tier card's large price, which follows the selected billing option

**Without JS the page still works**: the first image and the first price are rendered server-side, and the
tier form submits normally.

## SEO and link embeds

Both pages extend `templates/frontend/store/base.html.twig`, which replaces the platform's meta tags with
title, description, canonical, Open Graph and Twitter tags, fed by `metaDescription`, `metaImage`,
`metaCanonical` and `metaType`. (The shape is borrowed from forumify.net's marketplace, which got this part
right.)

Product pages additionally get:

- a 1200×630 `ecommerce_social` crop of the cover image, as `summary_large_image`
- `og:type=product` with price tags
- schema.org `Product` JSON-LD, with one `Offer` per active price; recurring prices get a
  `UnitPriceSpecification`

A `?category=` link gets that category's title, description and canonical.

## Styling and theming

- Styles are in `public/styles/store.css` and use **only theme variables**, so a community's theme carries through.
  It is linked site-wide from `templates/bundles/Forumify/frontend/base.html.twig`, because the CMS widgets
  reuse these classes on pages that are not store pages — see [cms-widgets.md](cms-widgets.md).
- Images go through the `ecommerce_tile`, `ecommerce_product`, `ecommerce_thumb` and `ecommerce_social`
  LiipImagine filters.
- Templates expose named blocks — `ecommerce_store_*`, `ecommerce_product_*`, `ecommerce_tier_*`,
  `ecommerce_category_*`, `ecommerce_plan_*` — so themes and other plugins have somewhere to hook in.
  `ecommerce_tier_checkout` is the block that owns the buy button, `ecommerce_plan_actions` the one that
  owns a subscription's actions.
- The buyer's pages reuse the store's own vocabulary: the plan card shows its price with the same
  `.ecommerce-amount` treatment as a tier card, and `.ecommerce-product-tile-image` supplies the thumbnail
  placeholder. See [checkout-and-orders.md](checkout-and-orders.md#buyer-pages).

## Code

- `src/Controller/StoreController.php`, `src/Component/ProductList.php`
- `templates/frontend/store/`, `templates/frontend/components/product_list.html.twig`
- The catalog tile lives in `templates/frontend/store/_product_tile.html.twig`, shared with the CMS widgets
- `public/styles/store.css`, `assets/dist/product_controller.js`
- Tests: `StorefrontTest` (catalog, category filter + description, search, gallery/tiers/prices, draft 404),
  `PriceFormatterTest`, `ProductTierTest`, `ProductTest`
