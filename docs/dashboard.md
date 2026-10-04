# Dashboard

One admin page that answers the two questions a store owner opens the admin with: **"is there anything I
have to do?"** and **"how is the store doing?"**. Both answers used to be scattered: the revenue graph and
the plan's limit sat on top of the orders list, and the work that was waiting hid in menu labels
("Orders (3)", "Refunds (1)", "Subscriptions (2)").

## What it replaced

| Before | On the dashboard |
| --- | --- |
| `Ecommerce\RevenueGraph` on top of the orders list | A **plan usage** card and a **revenue** chart |
| "Orders (n)" for users with `orders.fulfill` | A **"Paid orders to process"** list |
| "Refunds (n)" | A **"Refund requests"** list |
| "Subscriptions (n)" for users with `subscriptions.manage` | A **"Cancellations to handle"** list |
| — | **MRR** of the subscriptions, with the part at risk |
| — | **Product breakdown**: what sold, how much, compared to the period before |
| — | **Top customers**: who spends the most in the store |

The menu labels are plain "Orders", "Refunds", "Subscriptions", and the menu builder has no repositories or
`Security`: building the menu no longer runs three `COUNT` queries on every admin page.

> **Decision: a list instead of a number.** A count says *that* there is work, the list says *what* it is
> and lets an admin act from one place. The count was only ever a stand-in, because the platform menu has
> no badges (see [checkout-and-orders.md](checkout-and-orders.md)). The number survives as the list's
> header ("Paid orders to process · 3").

## Route, menu and access

- `DashboardController` at `admin/ecommerce/` → route `ecommerce_admin_dashboard`.
- "Dashboard" (`ph ph-squares-four`) becomes the **first** item in the Ecommerce menu.
- **One new permission for the whole page**, `ecommerce.admin.dashboard`, declared right after
  `ecommerce.admin.view` in `getPermissions()`. It guards the route, the menu item and every widget's
  Live component (`#[IsGranted]`, since Live components can be called on their own).

> **Decision: one permission for the whole dashboard.** Per-widget permissions (the fulfill permission for
> the orders queue, a reports permission for the money, …) would let a page show a different subset to every
> role, which is more to build, test and explain than it is worth. Whoever is trusted with the dashboard sees
> all of it. A side effect: the revenue figures were visible to everyone with `orders.view` while they sat on
> the orders page; now only `ecommerce.admin.dashboard` sees them.

> **Decision: our own page, not widgets on the platform dashboard.** The platform's
> `admin/dashboard/dashboard.html.twig` is a fixed grid with no extension point. A plugin-side page works
> today; contributing a summary card to the platform dashboard needs a platform change first (see
> [roadmap.md](roadmap.md)).

## Layout

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Dashboard                                                                    │
├─────────────────────────┬─────────────────────────┬──────────────────────────┤
│ MRR €1,240              │ Revenue this month      │ Active subscriptions     │  KPI row
│ €90 at risk, 3 past due │ €2,050  ↗ 12%           │ 84                       │
├─────────────────────────┴──────────┬──────────────┴──────────────────────────┤
│ Plan usage            [Merchant]   │ Revenue, last 12 months                 │
│ €3,100  ▓▓▓▓▓▓▓▓░░░ 67%            │   ╭─╮      ╭──╮                         │
│ of the €4,600 your plan allows     │ ──╯ ╰──────╯  ╰──                       │
├────────────────────────────────────┴─────────────────────────────────────────┤
│ Needs your attention                                                         │
├─────────────────────────┬─────────────────────────┬──────────────────────────┤
│ Paid orders to process 3│ Refund requests · 1     │ Cancellations to handle 0│
│ A1B2C3 Hoodie   4 days  │ D4E5F6 €25.00   2 days  │ All caught up.           │
├─────────────────────────┴─────────────────────────┴──────────────────────────┤
│ Sales                                                                        │
├──────────────────────────────────────────────────────────────────────────────┤
│ Products                                                    [Last month ▾]   │
│  Product        Orders (new / renewals)   Revenue    Share        vs before  │
│  VIP rank       41 (12 / 29)              €1,640     ▓▓▓▓▓▓ 54%   ↗ 8%       │
├──────────────────────────────────────────────────────────────────────────────┤
│ Top customers                                                 [All time ▾]   │
│  (o) alice      14       €420      2 active        Mar 2025                  │
└──────────────────────────────────────────────────────────────────────────────┘
```

The page is `templates/admin/dashboard/dashboard.html.twig` with one Twig block per region
(`ecommerce_dashboard_kpis`, `ecommerce_dashboard_revenue` for plan usage and the chart,
`ecommerce_dashboard_actions`, and `ecommerce_dashboard_sales` around `ecommerce_dashboard_products` and
`ecommerce_dashboard_customers`), so a store or another plugin can add, replace or drop a section. The KPI
cards have their own blocks inside `kpis.html.twig`. Cards on one row stretch to the same height (`h-100`),
and the work lists and the sales tables each sit under their own heading so they don't read as one group.

> **Decision: blocks, not a widget registry.** A tagged `DashboardWidgetInterface` with positions and sizes
> is a framework for a page with half a dozen cards. Template blocks already give other plugins a place to
> hook in. Revisit when a second plugin actually wants to add cards.

## The widgets

### KPI row

`Ecommerce\Dashboard\Kpis`, a plain Twig component: MRR (with the part at risk), revenue this month against
the same days of last month, and the number of active subscriptions.

### Needs your attention

Three lists, one Live component each, built on `AbstractDoctrineList` like the platform's `LatestTopics`.
They sit side by side, each showing the **oldest first** (the one waiting longest is the most urgent), five
rows, pagination, how many days it has been waiting (the exact date on hover), and a link to the page where
the work is done. An empty list collapses to one "All caught up" line instead of disappearing, so the admin
knows it was checked. The title carries the
count ("Paid orders to process · 3"); `AbstractDoctrineList` counts through the list's own query, so the old
`countToFulfill()` / `countPending()` repository methods are gone.

| Component | Query | Row | Links to |
| --- | --- | --- | --- |
| `OrdersToProcess` | `Order.status = Paid`, by `paidAt` | reference, product + tier, buyer, fulfillment type, age | order page |
| `PendingRefunds` | `RefundRequest.status = Pending`, by `createdAt` | order reference, amount, buyer, reason (truncated), age | order page (where it is approved) |
| `PendingCancellations` | `CancellationRequest.completedAt IS NULL`, by `createdAt` | product + tier, buyer, `currentPeriodEnd`, age; "Overdue" once the period has run out | subscription page |

A `Paid` order of a **non-manual** fulfillment type means an integration failed to complete it. It is in the
same list, with a "Stuck" tag, because it is the same work: an admin has to look at it and mark it
completed (see [fulfillment.md](fulfillment.md)).

### Plan usage and revenue

What used to be `Ecommerce\RevenueGraph` on the orders page, split along the seam that was already in its
template:

- **Plan usage**: `Ecommerce\Dashboard\PlanUsage`, a card with the tier, the rolling average against the
  cap, the progress bar and the near/over cap warning, from `RevenueCapService::getUsage()`. It sits next
  to the chart, the two figures that are about the store's revenue over time.
- **Revenue** (chart): `Ecommerce\Dashboard\RevenueGraph`, revenue per month for the last twelve months
  from `RevenueCapService::getMonthlyRevenue()`.

The orders page is just the table. The "View revenue" link in the revenue cap notifications leads here.

### MRR

**Monthly recurring revenue**: what the store's subscriptions bring in per month if nothing changes.

- **Which subscriptions:** `Active` and `PastDue`. `Cancelled` ones still run until their period ends but
  will not renew, so they are no longer *recurring*. `Ended` ones are gone.
- **The amount:** the subscription's `initialOrder` snapshot (`amountMinor`, `currency`, `billingInterval`,
  `intervalCount`). Subscribers are grandfathered on their price, so that is what every renewal charges.
- **Normalised to a month** by `BillingInterval::getTimesPerMonth($count)`: `month ÷ n`, `year ÷ 12n`,
  `week × 52 ÷ 12n`, `day × 365 ÷ 12n`, summed as `RationalMoney`, converted to the store currency through
  `CurrencyConverter`, rounded **once**.
- **At risk:** the `PastDue` part is counted in the MRR but flagged below the figure ("€90 at risk,
  3 subscriptions past due"), linking to the subscription list filtered on past due
  (`/admin/ecommerce/subscriptions?status=past_due`; the list passes `status` to the table's filter).

`MrrService::getMrr()` returns an `Mrr` (total, at risk, subscriptions, past due).
`SubscriptionRepository::sumOpenByInterval()` groups the open subscriptions by status, currency, interval
and interval count and sums the amounts, so the work is a handful of rows however many subscribers there
are; the normalisation happens in PHP.

> **Decision: snapshot amount, not `Subscription.price`.** `price` is `SET NULL` when a price is deleted
> while subscribers keep paying it, and it is a link to the catalog, not a record of what is charged. When
> "moving active subscriptions to a new price" ([roadmap.md](roadmap.md)) is built, the amount has to come
> from wherever that feature records the new charge.

> **Decision: MRR is gross.** Provider fees and taxes are not known to the plugin, and refunds of past
> renewals do not change what will be charged next month.

There is no MRR **history**: the status changes of a subscription are not stored, so last month's MRR can't
be reconstructed. See "MRR trend" in [roadmap.md](roadmap.md).

### Periods

`SalesPeriod` is shared by the product breakdown, top customers and the "revenue this month" card. Dates
are calendar months in the viewer's timezone (`TimezoneResolver`), converted to the server's timezone for
the query. Ranges are `[from, to)`.

| Period | Range | Compared against |
| --- | --- | --- |
| `this_month` | the 1st of this month to the 1st of next | the same number of days of last month, at most all of it |
| `last_month` | last month, complete | the month before |
| `last_3_months`, `last_12_months` | that many complete months, not counting this one | the same number of months before |
| `all_time` | everything | nothing |

> **Decision: complete months.** A partial month next to a complete one makes every product look like it is
> collapsing. Only "this month" is partial, and it is compared with the same days of the month before.

### Product breakdown

`Ecommerce\Dashboard\ProductBreakdown`: what sold in a period, per product, best seller first.

- **Period:** a `<select>` on a writable Live prop, `last_month` by default (a complete month is the honest
  comparison). `all_time` is not offered: there is nothing to compare it with.
- **What counts:** the same as revenue: orders `Paid` or `Completed` by `paidAt`. Renewals count, split out
  so a product that lives off its subscribers is recognisable.
- **Grouped by the snapshot** `productKey`, not the `product` link, so a deleted product still shows up,
  under the title of its most recent sale. A product with more than one tier expands into its tiers
  (grouped by `tierKey`) through a `toggle` Live action.
- **Columns:** product, orders (new / renewals), revenue in the store currency, share of the period's
  revenue (a bar), and the change against the period before. A product that did not sell before reads
  "New". Free orders add to the order count but not to the revenue.
- **Sorted** by revenue, then orders.

`OrderRepository::sumSalesByProduct($from, $to)` returns rows per product, tier, title, currency and
new/renewal with count, sum and last paid date. `ProductSalesService::getReport()` converts and merges the
currencies, adds the previous period and computes the shares into a `ProductSalesReport` of `ProductSales`.
The `(status, paidAt)` index covers the range.

### Top customers

`Ecommerce\Dashboard\TopCustomers`: the buyers who spent the most, so a community store knows who its
biggest supporters are — people worth thanking, and a natural fit for a badge grant.

- **Period:** every `SalesPeriod`, with `all_time` as the default: loyalty is a long-term thing.
- **What counts:** the same as revenue (`Paid` or `Completed`, by `paidAt`, refunds excluded), grouped by
  `Order.user`. Orders whose user was deleted (`SET NULL`) are left out: there is no one to show.
- **Columns:** avatar and name (linking to the platform's admin user page), orders, revenue in the store
  currency, open (active or past due) subscriptions, and "customer since" (their first paid order, whatever
  the period). Ten rows.
- **Sorted** by revenue, then orders.

`OrderRepository::findTopCustomers($from, $to, $limit)` ranks and returns the top buyers' totals per
currency; `TopCustomerService` converts them, loads the users and counts their subscriptions
(`SubscriptionRepository::countOpenByUser()`).

> **Decision: rank in SQL, convert exactly in PHP.** Ranking needs one currency, but loading every buyer's
> totals to convert them in PHP does not scale for "all time". The query ranks on US dollars through a join
> on `ecommerce_exchange_rate` (per-USD rates, so `amount ÷ rate`, missing rates at face value like
> `CurrencyConverter`), takes the top ten, and the displayed revenue is then converted exactly. Ranking in
> dollars gives the same order as ranking in the store currency. In a single-currency store, which is
> nearly all of them, the join changes nothing.

## Code

- `src/Admin/Controller/DashboardController.php`, `templates/admin/dashboard/`, `public/styles/admin.css` (table
  cells lined up with the card titles)
- `src/Admin/Component/Dashboard/` — `Kpis`, `PlanUsage`, `RevenueGraph`, `OrdersToProcess`, `PendingRefunds`,
  `PendingCancellations`, `ProductBreakdown`, `TopCustomers`
- `src/Revenue/` — `MrrService`, `Mrr`, `SalesPeriod`, `ProductSalesService`, `ProductSalesReport`,
  `ProductSales`, `TopCustomerService`, `TopCustomer`
- `OrderRepository::sumSalesByProduct()`, `findTopCustomers()`; `SubscriptionRepository::sumOpenByInterval()`,
  `countOpenByUser()`; `BillingInterval::getTimesPerMonth()`
- Tests: `DashboardTest` (every widget, the permission, plain menu labels), `MrrServiceTest`,
  `SalesPeriodTest`, `ProductSalesServiceTest`, `TopCustomerServiceTest` (a `KernelTestCase`: the ranking is
  SQL). `FulfillmentTest`, `RefundTest`, `CancellationTest` and `RevenueCapTest` check their part on the
  dashboard.

## Open questions

- **Does the dashboard replace the plugin's settings link?** `getPluginMetadata()` points at the
  configuration page. It is the *settings* route, so it stays for now.
