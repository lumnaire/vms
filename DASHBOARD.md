# Dashboard Computations and Formulas

This document explains the implemented vendor, staff, and supervisor dashboard computations in the Virac Market System for researchers. It describes the existing application behavior and provides examples that can be reproduced manually.

## 1. Data sources and scope

| Source | Fields used | Purpose |
| --- | --- | --- |
| `vendor_inventories` | `vendor_id`, `fish_type_id`, `quality_class`, `entry_date`, `status`, `stock_kg`, `released_kg`, `sold_kg`, `pulled_out_kg`, `price_per_kg`, `tariff_price`, `disposed_at`, `carried_out_at` | Batch counts, declared supply, remaining stock, price comparisons, and stock outcomes |
| `batch_releases` | `vendor_inventory_id`, `vendor_id`, `kind`, `kg`, `created_at` | Dated records of sold quantities and unsold withdrawals |
| `price_guides` | `fish_type_id`, `quality_class`, `tariff_price`, `is_active` | Current official tariff; previous versions remain in history |
| `users` | `role`, `status` | Active vendor and staff counts |
| `vendor_profiles` | `user_id`, `stall_number` | Stall count and identification |

Vendor computations are limited to the authenticated vendor's ID. Staff and supervisor computations cover the market. Active account counts filter `status = active`; market supply and sales queries do not automatically exclude historical records belonging to inactive users.

The application timezone defaults to `Asia/Manila` and can be overridden by `APP_TIMEZONE`. “Today” follows that configuration. Supply uses the batch's `entry_date`; sales use the date of the release's `created_at`. SQL extracts those stored dates directly, without an explicit timezone conversion in the analytics query.

### Symbols and units

| Symbol | Meaning | Unit |
| --- | --- | --- |
| `W_i` | Declared stock of batch i (`stock_kg`) | kg |
| `R_i` | Stock initially made available (`released_kg`) | kg |
| `S_i` | Cumulative sold quantity (`sold_kg`) | kg |
| `U_i` | Cumulative unsold withdrawal (`pulled_out_kg`) | kg |
| `P_i` | Batch selling price (`price_per_kg`) | PHP/kg |
| `T_i` | Tariff saved on that batch (`tariff_price`) | PHP/kg |
| `q_j` | Quantity in sold release j (`batch_releases.kg`) | kg |

`SUM(...)` means add the indicated values over all qualifying records. `COUNT(...)` counts records, rather than fish types or vendors. Each repeated submission is a separate batch.

## 2. Shared computations

### 2.1 Confirmed supply

```text
Supply on date d = SUM(W_i where status = confirmed and entry_date = d)
Supply for a period = SUM(daily supply within the period)
```

Supply measures fish brought in and confirmed. Selling, withdrawing, or expiring that fish does not subtract it from historical supply. Pending and rejected batches do not contribute.

### 2.2 Sold kilograms and sales value

```text
Sold kg on date d = SUM(q_j where kind = sold and DATE(created_at) = d)
Sales on date d = SUM(q_j * P_i for those sold releases joined to their batches)
```

Only releases recorded as `sold` generate sales. A `pulled_out` release reduces remaining stock but creates no sales. The query uses the linked batch's price, rather than a separate transaction price stored on the release. The sales query itself does not add a batch-status filter; the normal release workflow requires confirmation before selling.

### 2.3 Markup and tubo

```text
Markup per kg = P_i - T_i
Markup percentage = ((P_i - T_i) / T_i) * 100
Tubo for a sold release = q_j * (P_i - T_i)
Tubo for a period = SUM(tubo for sold releases recorded in the period)
```

Historical tubo uses the tariff saved when the batch was submitted. Updating the current tariff does not replace that saved value. A batch with a null tariff contributes zero to tubo, while its sold quantity still contributes to sales. A price below the saved tariff produces negative tubo; it is not clamped to zero.

**Research interpretation:** “Tubo” in this system means the value of the selling-price difference relative to the official tariff on quantities sold. It is not accounting net profit: purchase cost, transport, ice, labor, rent, and other expenses are not deducted.

For comparison badges, `PriceGuide::compare()` returns no comparison if the tariff is missing or nonpositive. It rounds `P - T` to two decimals and the percentage to one decimal. The rounded difference is above tariff when greater than `0.004`, below when less than `-0.004`, and otherwise at tariff.

### 2.4 Remaining stock

```text
Remaining_i = MAX(0, R_i - S_i - U_i)
```

Remaining stock is zero for disposed/expired batches and legacy carried-out batches. For current submissions, `released_kg` starts equal to `stock_kg`; despite its name, it represents the initial available quantity, not cumulative sales.

An open batch is confirmed, has neither disposal nor legacy carry-out recorded, and has `released_kg > sold_kg + pulled_out_kg`. Open stock may include batches submitted on previous dates.

### 2.5 Percentage change and month-to-date comparisons

```text
Change (%) = ((Current - Previous) / Previous) * 100, if Previous > 0
Change = null, if Previous <= 0
```

The result is rounded to one decimal. A null comparison displays “No figure to compare with yet.” A previous value of zero does not produce a numeric percentage, even if the current value is also zero.

Month-to-date (MTD) totals cover the first day of this month through today, inclusive. The comparison covers the first day of the previous month through the same day number, capped at that month's last day. For example, March 31 compares March 1–31 with February 1–28 (or 29 in a leap year), not the full previous month in every case.

### 2.6 Time-series chart aggregation

| Toggle | Window | Each plotted value |
| --- | --- | --- |
| Daily | Today and the preceding 29 days | Sum for one calendar date |
| Monthly | Current month and preceding 11 months | Sum for one calendar month |
| Quarterly | Current quarter and preceding 7 quarters | Sum for one calendar quarter |
| Yearly | Current year and preceding 4 years | Sum for one calendar year |

Quarters are January–March, April–June, July–September, and October–December. Buckets appear oldest first, missing buckets contain zero, and series exclude dates after today. The current bucket is incomplete until its period ends. Aggregation sums values; it does not average daily totals. The default chart toggle is monthly unless a saved viewer preference exists.

Changing the toggle changes the charts and associated period breakdowns. MTD cards, today's counts, and all-time records retain their own date definitions.

### 2.7 Stock outcomes and sell-through

For the chosen window, select confirmed batches with `entry_date >= window start`. These are batch-cohort outcomes: their cumulative disposition is measured now, regardless of when each release happened.

```text
Sold = ROUND(SUM(S_i), 2)
Pulled out = ROUND(SUM(U_i), 2)
Expired = ROUND(MAX(0, SUM(R_i - S_i - U_i for disposed or carried-out batches)), 2)
On stall = ROUND(MAX(0, SUM(R_i) - Sold - Pulled out - Expired), 2)
Gone = Sold + Pulled out + Expired
Sell-through (%) = ROUND(Sold / Gone * 100, 1), if Gone > 0
```

When `Gone = 0`, sell-through is null and displayed as a dash. Its denominator is stock that has left the stall; unsold stock still on the stall is excluded. This differs from a sold/initial-stock ratio. The outcome doughnut uses Sold, Pulled out, and Expired. Each tooltip percentage is that category's quantity divided by their total, multiplied by 100. The supervisor's class doughnut similarly divides each class's supply by total class supply.

**Date-boundary detail:** `stockOutcome`, `topSuppliers`, `supplyByClass`, and leaderboard queries enforce a starting date but do not explicitly cap their queries at today. Time-series bucket aggregation does enforce an upper date bound. Future-dated data could therefore make breakdown totals differ from series totals.

## 3. Vendor dashboard

Source: [Vendor DashboardController](app/Http/Controllers/Vendor/DashboardController.php).

| Metric or panel | Computation and inclusion rules |
| --- | --- |
| Today's entries | Count the vendor's batches with `entry_date = today`, regardless of status |
| Confirmed / pending entries today | Count today's batches separately for `confirmed` and `pending` status |
| Remaining stock | Sum `Remaining_i` over all the vendor's open batches, including earlier dates |
| Sales today | Sum sold-release sales dated today |
| Sales change | Compare today's sales with yesterday's using the shared change formula |
| Sales this month | MTD sales, compared with the same days of last month |
| Tubo this month | MTD tubo based on sold releases and saved batch tariffs |
| Best sales day | Date with the largest positive daily sales total over all available history; no positive total returns no record |
| Sales and tubo charts | Vendor-only series aggregated using the selected chart period |
| Stock outcome / sell-through | Shared outcome computation for this vendor's confirmed batches in the selected cohort |
| Today's inventory table | Today's batches of all statuses, ordered by fish type and batch number; confirmed rows show remaining kilograms |

The controller also prepares a price-comparison dataset (`myPrices`) grouped by fish ID and quality class. It uses the highest price among the vendor's open batches, compares it with the **current** tariff, and supplies the market minimum, maximum, distinct vendor count, and highest-markup indicator. The current vendor dashboard template does not render a separate `myPrices` panel.

The inventory table's freshness countdown is `MAX(0, freshnessDays - batchAgeInDays)`. Batch age is the number of calendar days from `entry_date` to today. `freshnessDays` comes from `inventory.stale_after_days`, with a minimum of one day.

## 4. Staff dashboard

Source: [Staff DashboardController](app/Http/Controllers/Staff/DashboardController.php).

| Metric or panel | Computation and inclusion rules |
| --- | --- |
| Pending today | Count today's batches currently pending across vendors |
| Confirmed today | Count today's batches currently confirmed |
| Rejected today | Count today's batches currently rejected |
| Active vendors | Count users with `role = vendor` and `status = active` |
| Pending preview | Latest five pending batches whose entry date is today; this list is shorter than the full pending count when more than five exist |
| Last seven days' entries | For each date from today minus six days through today, count batches by current confirmed/rejected/pending status; missing counts are zero |
| Vendors without submissions | Active vendor users with no inventory batch dated today; any submitted status means they have submitted |
| Above tariff today | Count today's pending or confirmed batches with selling price greater than the saved tariff; if saved tariff is null, use the current matching tariff; exclude missing-tariff and rejected batches |
| Supply this month | MTD confirmed supply and percentage change versus the same days of last month |
| Supply chart | Market-wide confirmed kilograms aggregated by selected period |
| Markup leaderboard | Up to eight vendors ranked using the current month's qualifying batches; sales and tubo totals are omitted |

“Confirmed today” refers to a batch entered today with confirmed status, rather than a count of confirmation actions performed today. Historical weekly bars also reflect current status, rather than preserving the status a batch had on each earlier day.

### Repeated submissions

Group today's batches by `(vendor_id, fish_type_id, quality_class)` and retain groups containing more than one batch, including all statuses in group membership.

```text
Confirmed kg per group = SUM(stock_kg of confirmed batches in the group)
Pending kg per group = SUM(stock_kg of pending batches in the group)
Pending count per group = COUNT(pending batches in the group)
```

Groups containing pending work appear first, then sort by vendor name. Rejected rows can appear in a group but add to neither confirmed nor pending kilograms. Multiple batches indicate repeated submissions and do not by themselves establish duplicate or invalid supply.

## 5. Supervisor dashboard

Source: [Supervisor DashboardController](app/Http/Controllers/Supervisor/DashboardController.php).

| Metric or panel | Computation and inclusion rules |
| --- | --- |
| Active vendors | Count active users with vendor role |
| Total stalls | Count all `vendor_profiles` rows; no active-user filter or distinct-stall-number calculation |
| Active staff | Count active users with staff role |
| Today's confirmed stock | Sum `stock_kg` of confirmed batches entered today; this is supplied stock, not remaining stock |
| MTD supply | Confirmed supply from month start through today |
| MTD sales | Sold-release quantity times linked batch price, from month start through today |
| MTD sold kg | Sum sold-release kilograms from month start through today |
| MTD tubo | Sum sold-release quantity times the saved tariff difference, from month start through today |
| KPI change badges | Compare each MTD measure with the same days of the previous month |
| Supply, sold, sales, tubo trends | Market-wide daily measures summed into the selected chart buckets |
| Top suppliers | Sum confirmed `stock_kg` by vendor since the selected window start, sort descending, show the top five |
| Supply by class | Sum confirmed `stock_kg` by quality class since the window start; include all configured classes with zero where absent |
| Stock outcomes | Shared cohort outcome and sell-through calculation across vendors |
| Tubo leaderboard | Up to eight vendors, using the rules below |
| Biggest supply day | Highest positive daily confirmed-supply total over all history |
| Biggest sales day | Highest positive daily sales total over all history |
| Highest price record | Confirmed batch with maximum `price_per_kg`; equal prices sort by earliest entry date |
| Highest markup record | Confirmed batch with a saved tariff and positive `P_i - T_i`, ranked by the largest peso/kg difference |
| Markup watch | From current open-stock groups, take the five largest positive markup percentages against current tariffs |

Best-day computations return the first encountered maximum if daily values tie; the underlying grouped query does not explicitly order tied days. Researchers should not assume a defined earliest/latest tie-break for those records.

## 6. Leaderboard and live-board formulas

### 6.1 Vendor markup leaderboard

For each vendor, use confirmed batches entered since month start with a saved tariff greater than zero.

```text
Batches = COUNT(qualifying batches)
Above tariff = COUNT(qualifying batches where P_i > T_i)
Weighted average markup (PHP/kg) = SUM((P_i - T_i) * W_i) / SUM(W_i)
Weighted average markup (%) = SUM(((P_i - T_i) / T_i) * W_i) / SUM(W_i) * 100
Maximum markup (PHP/kg) = MAX(P_i - T_i)
```

If total stock weight is zero, both weighted averages return zero. Weights are declared stock, rather than sold kilograms or remaining kilograms. Percentage markup is the weighted average of each batch's percentage; it is not computed by dividing average peso markup by an arbitrary tariff.

Staff rankings sort by weighted average peso/kg markup descending, then maximum markup descending. Supervisor rankings sort by tubo descending, then weighted average peso/kg markup descending.

Supervisor leaderboard tubo sums sold releases recorded since month start for batches with a non-null saved tariff. These releases may come from batches entered before the month. However, the displayed vendor list is built from the qualifying current-month markup batches. A vendor with only sales from older batches can therefore contribute to the MTD sales/tubo cards without appearing in this leaderboard. Leaderboard totals are not an exhaustive reconciliation of market-wide KPI cards.

### 6.2 Current prices and tariff comparison

`MarketAnalytics::boardByFish()` groups open batches by fish ID and quality class:

```text
Market minimum price = MIN(P_i among the group's open batches)
Market maximum price = MAX(P_i among the group's open batches)
Market average price = SUM(P_i) / COUNT(open batches)
Available kg = SUM(remaining stock of the group's open batches)
Stalls selling = COUNT(distinct vendor IDs in the group)
Each stall's representative price = MAX(P_i for that vendor in the group)
```

The average is an unweighted batch average. The live board compares prices with current tariffs, while historical tubo uses saved batch tariffs. A stall is flagged as highest markup only when its representative price is above the current tariff. The supervisor markup watch sorts these group winners by percentage, whereas the all-time highest-markup record ranks by pesos per kilogram.

## 7. Worked examples

### 7.1 One batch: supply, sales, tubo, and stock

Assume a confirmed batch has 20 kg of initial stock, a price of PHP 150/kg, and a saved tariff of PHP 120/kg. The vendor records 8 kg sold and 2 kg pulled out.

| Computation | Substitution | Result |
| --- | --- | --- |
| Confirmed supply | 20 | 20 kg on the entry date |
| Sales | 8 * 150 | PHP 1,200 on the sold-release date |
| Markup | 150 - 120 | PHP 30/kg |
| Markup percentage | 30 / 120 * 100 | 25.0% |
| Tubo | 8 * 30 | PHP 240 |
| Remaining stock | 20 - 8 - 2 | 10 kg |
| Sell-through before expiry | 8 / (8 + 2) * 100 | 80.0% |

If the remaining 10 kg expires, expired stock becomes 10 kg and on-stall stock becomes zero. Sell-through becomes `8 / (8 + 2 + 10) * 100 = 40.0%`. Historical supply remains 20 kg, sales PHP 1,200, and tubo PHP 240.

If this batch was submitted yesterday and sold today, yesterday receives the 20 kg supply, while today receives the PHP 1,200 sales. This is why same-day supply and sales do not necessarily describe the same fish.

### 7.2 Weighted markup across two batches

| Batch | Stock | Price | Saved tariff | Markup/kg | Markup % |
| --- | --- | --- | --- | --- | --- |
| A | 10 kg | PHP 150 | PHP 120 | PHP 30 | 25% |
| B | 30 kg | PHP 110 | PHP 100 | PHP 10 | 10% |

```text
Weighted markup = (30 * 10 + 10 * 30) / (10 + 30) = PHP 15.00/kg
Weighted percentage = (0.25 * 10 + 0.10 * 30) / 40 * 100 = 13.8% after rounding
Above-tariff batches = 2
Maximum markup = PHP 30.00/kg
```

### 7.3 Change comparison

If MTD sales are PHP 15,000 and the comparable previous-month sales are PHP 12,000, change is `(15,000 - 12,000) / 12,000 * 100 = 25.0%`. If the previous total is zero, the comparison is unavailable.

## 8. Precision and interpretation for research

Stored monetary and quantity fields use two decimal places. Chart buckets, date-range sums, outcome quantities, and leaderboard peso figures round to two decimals; comparison and sell-through percentages round to one. Templates may display kilograms with fewer decimals and axes may abbreviate large numbers, so rounded screen labels may not add exactly to the underlying totals.

These dashboards describe recorded submissions and vendor-recorded releases. They do not independently measure actual consumer purchases, unrecorded sales, costs, or net profit. Status changes, corrected batch prices, and later releases can change derived historical results. Stock-cohort charts and release-date charts answer different questions and should be identified separately in research tables.

## 9. Implementation references

- [Shared analytics service](app/Services/MarketAnalytics.php): `dailySupply`, `dailySales`, `monthToDate`, `change`, `bucketize`, `stockOutcome`, `tuboLeaderboard`, and live-board/record methods.
- [Inventory model](app/Models/VendorInventory.php): `scopeOpen`, `getRemainingStock`, `release`, tariff helpers, and freshness methods.
- [Tariff model](app/Models/PriceGuide.php): `tariffMap`, `tariffFor`, `setTariff`, and `compare`.
- Dashboard templates: [Vendor](resources/views/vendor/dashboard.blade.php), [Staff](resources/views/staff/dashboard.blade.php), and [Supervisor](resources/views/supervisor/dashboard.blade.php).
- [Shared chart kit](resources/views/partials/chart-kit.blade.php) and [change badge](resources/views/components/delta.blade.php): display formats and tooltip percentages.
- [Consumer board](resources/views/public/priceboard.blade.php): public tariff and stall-price comparisons.
- [Analytics tests](tests/Feature/TariffAnalyticsTest.php) and [batch stock tests](tests/Feature/BatchStockTest.php): executable examples of calculation behavior.
