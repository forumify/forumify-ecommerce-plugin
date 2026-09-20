# CMS widgets

Blocks that admins can drop onto a CMS page in the platform's page builder, so a community can build its
own storefront landing page instead of sending everyone to `/store`.

All five widgets implement `Forumify\Cms\Widget\WidgetInterface` (via the platform's `AbstractWidget`),
which is auto-tagged, so they need no registration. They share the category `ecommerce`, which the page
builder shows as an "Ecommerce" group in the widget sidebar.

## The widgets

### `ecommerce.product` — a single product

Renders one product as the exact tile the catalog uses. Settings:

| Setting | Effect |
| --- | --- |
| `product` | The product, stored by its **`key`** |
| `hideImage`, `hideDescription`, `hidePrice` | Drop that part of the tile |

Only **published** products render; a draft or deleted one makes the widget render nothing rather than
link to a page that 404s. The settings form lists every product, including drafts, so a page can be built
before the product goes live.

> **Decision: reference the product by `key`, not by id.** `key` is already the plugin's stable external
> identifier (fulfillment, grants, order snapshots all use it), it is unique, and it survives a database
> re-import — a page built on staging keeps working when the content is moved. The platform's own widgets
> store ids, so this deviates on purpose.

> **Decision: the toggles hide rather than show.** Form checkboxes default to unchecked, so a freshly
> dropped widget with `showImage`/`showPrice` settings would render an empty tile. Phrasing them as
> "hide" makes the default a complete tile.

### `ecommerce.product_category` — products from one category

A heading, a grid of tiles, and a "Show more" link to `/store?category=<slug>`. Settings:

| Setting | Effect |
| --- | --- |
| `category` | The category, stored by slug |
| `limit` | How many tiles to show (default 4, clamped to 1–48) |
| `hideTitle` | Drop the heading |

`limit + 1` products are fetched; the extra one never reaches the template, it only decides whether the
"Show more" link is rendered. That is one query instead of a separate `COUNT`.

The widget renders nothing when the category is gone or holds no published products.

### `ecommerce.pricing_table` — one product's tiers

The product page's tier comparison as a standalone block: every purchasable tier side by side, with the
billing-option radios and working buy buttons. Settings: `product` (by key), `title` (optional heading).

It includes `_tier.html.twig` unchanged and wraps the grid in the `product` Stimulus controller, which is
all `selectPrice` needs (its gallery targets are optional), so the large amount still follows the selected
billing option. A product with no purchasable tier renders nothing.

> **Decision: reuse `_tier.html.twig` rather than a widget-specific pricing card.** The tier card already
> carries the checkout form, the price radios, the highlight badge and the `ecommerce_tier_*` blocks. A
> second implementation would drift, and the buy path is the part that must not.

### `ecommerce.buy_button` — a single call to action

A button straight to checkout for one tier, meant to sit next to a Rich Text or Box widget in a
hand-built hero. Settings:

| Setting | Effect |
| --- | --- |
| `tier` | The tier, stored as `<product key>::<tier key>` |
| `label` | Button text, defaults to "Get &lt;tier&gt;" |
| `type` | `btn-cta` / `btn-primary` / `btn-outlined` / `btn-link`, same values as the platform's Button widget |
| `showPrice` | Also render the resolved price under the button |

It links to `ecommerce_checkout_select`, which is a plain GET, so no form is needed. Checkout itself is
`IsGranted('ROLE_USER')`, so anonymous visitors land on the login page exactly as they do from the store.

> **Decision: store `product key :: tier key`, and never a price id.** A settings form cannot cascade
> product → tier → price, so one flat choice list ("Supporter - Premium") keeps it to a single field. More
> importantly, `PriceChangeService` *replaces* a locked price with a new row rather than editing it, so a
> stored price id would silently point at a replaced price the first time an admin changes the amount.
> The tier is stable; the price is resolved at render time as the tier's cheapest active price.

**When the tier has no active price**, the widget renders the button *disabled* with a `title` of
"This product is not available right now." rather than disappearing — a page that silently loses its call
to action is harder to notice than one showing a dead button. A tier that no longer exists at all (product
deleted or unpublished) still renders nothing, because there is no label left to show.

### `ecommerce.catalog_search` — a search bar

A plain `<form method="get" action="/store">` with a `q` field, a submit button and an optional
"Show all products" link. Settings: `title` (optional heading), `placeholder`, `hideBrowseAll`.

It needs no JavaScript and no live component: `ProductList` binds `query` to the `?q=` URL parameter
(`UrlMapping(as: 'q')`), and Live Components hydrate URL-bound props from the query string on first
render, so a normal GET lands on a pre-filtered catalog.

> **Decision: submit to `/store` instead of searching in place.** The widget is meant for a landing page
> (forumify.net's marketplace is the model), where search is an entry point into the catalog, not the
> catalog itself. Searching in place would mean a second product list with its own pagination on a page
> that already has one.

## The shared tile

`templates/frontend/store/_product_tile.html.twig` holds the tile markup — image, title, short
description, starting price. Both the catalog list (`ProductList`) and the product widgets include it, so
a tile looks the same everywhere and only has to be changed once.

It takes `product` plus `showImage`, `showDescription` and `showPrice` (all default `true`), and keeps the
`ecommerce_product_tile_image` and `ecommerce_product_tile_body` blocks, so a theme can override the
partial itself or embed it and replace one half.

## Styling

Widgets reuse the existing store classes (`ecommerce-product-grid`, `ecommerce-product-tile*`,
`ecommerce-store-search`) and the platform's utility classes for their own chrome, so they carry no
separate stylesheet. A CMS page is not a store page, though, so `store.css` has to be loaded site-wide:

`templates/bundles/Forumify/frontend/base.html.twig` overrides the platform's frontend base template and
appends the stylesheet to its `stylesheets` block. The platform's `ForumifyTemplateLoader` chains these
per-plugin overrides (each one extends the previous, ending at `!@Forumify/...`), so overriding a shared
template does not collide with other plugins doing the same.

> **Decision: load `store.css` globally rather than per widget.** The alternative was a `<link>` inside
> each widget template, which puts a render-blocking stylesheet in the middle of the body and repeats per
> widget on a page. `templates/frontend/store/base.html.twig` no longer links the stylesheet itself; the
> base override covers store pages too.

Page-builder **previews** are a different matter: they render inside the admin, where `store.css` is not
loaded. They are therefore hand-written HTML using only platform classes (`card`, `btn-link`, `flex`),
and approximate the widget rather than reproduce it.

## Code

- `src/Cms/Widget/` — `AbstractEcommerceWidget` (the shared `ecommerce` category), `AbstractProductWidget`
  (product lookup by key + the choice list), and the five widgets
- `ProductTierRepository::findPublishedByKeys()` and `::findAllForSelection()`
- `templates/frontend/cms/widgets/`, `templates/frontend/store/_product_tile.html.twig`
- `templates/bundles/Forumify/frontend/base.html.twig`
- `ProductRepository::findPublishedInCategory()` and `::hydratePrices()` (the latter shared with
  `ProductList`, which used to inline it)
- Widget names are translated under `admin.cms.widget.ecommerce.*`; their own field help lives under
  `ecommerce.cms.widget.*`
- Tests: `CmsWidgetTest` (all three on one page, search submits through to a filtered catalog, missing
  selections render nothing, pricing table + buy button through to checkout, a disabled button when the
  price is deactivated), `ProductWidgetTest`, `ProductCategoryWidgetTest`, `BuyButtonWidgetTest`
