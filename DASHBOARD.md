# Understanding the Dashboard Figures

This guide explains how the Virac Market System calculates the figures shown on the vendor, staff, and supervisor dashboards. It is written for researchers and research panel members, with everyday explanations and sample computations.

## 1. What information does the system use?

The dashboards use the fish deliveries submitted by vendors, the entries confirmed by staff, the quantities vendors record as sold or removed, and the official market tariff.

A **batch** is one submitted delivery of fish. If a vendor submits the same kind of fish twice, the system counts two batches.

| Term | Meaning |
| --- | --- |
| Supply | The weight of fish submitted by vendors and confirmed by staff |
| Sales | The peso value of fish recorded as sold |
| Tariff | The official reference price per kilogram set for a fish |
| Markup | The difference between the vendor's selling price and the tariff |
| Tubo | The markup multiplied by the quantity sold |
| Remaining stock | Fish still available for sale |
| Pulled-out stock | Fish removed from sale without being sold |
| Expired stock | Unsold fish taken off sale after reaching the allowed freshness limit |

Weights are measured in kilograms (kg), and money is measured in Philippine pesos (PHP).

**How to read the formulas:** Add (+) combines amounts, subtract (-) finds a difference, multiply (×) repeats an amount, and divide (÷) finds a share or average.

## 2. Basic calculations used by the dashboards

### A. Total supply

Add the weight of all confirmed batches for the chosen dates.

**Formula:** Total supply = Weight of the first confirmed batch + Weight of the second confirmed batch + Other confirmed batches

**Example:** Three confirmed deliveries contain 20 kg, 15 kg, and 10 kg.

**Computation:** 20 + 15 + 10 = **45 kg of supply**

Pending and rejected entries are excluded. Once a delivery is counted as supply, selling or removing some of it does not erase the original delivery from the supply record.

### B. Sales

Multiply the quantity recorded as sold by that batch's selling price per kilogram. When several batches are sold, add their sales values.

**Formula:** Sales = Kilograms sold × Selling price per kilogram

**Example:** A vendor records 8 kg sold at PHP 150 per kilogram.

**Computation:** 8 × 150 = **PHP 1,200 in sales**

Only fish recorded as sold contributes to sales. Fish removed without being sold does not generate sales. The calculation uses the selling price recorded for the batch.

### C. Markup compared with the tariff

Subtract the official tariff from the vendor's selling price.

**Formula:** Markup per kilogram = Selling price per kilogram - Tariff per kilogram

**Example:** The selling price is PHP 150 and the tariff is PHP 120.

**Computation:** 150 - 120 = **PHP 30 markup per kilogram**

The system can also express this difference as a percentage of the tariff.

**Formula:** Markup percentage = (Markup per kilogram ÷ Tariff per kilogram) × 100

**Computation:** (30 ÷ 120) × 100 = **25% above the tariff**

A lower selling price produces a negative difference, meaning the price is below the tariff. If no valid tariff is available, the system cannot show a percentage comparison.

### D. Tubo on fish sold

Multiply the markup per kilogram by the kilograms sold.

**Formula:** Tubo = Kilograms sold × (Selling price per kilogram - Tariff per kilogram)

**Example:** A vendor sells 8 kg at PHP 150 per kilogram, with a recorded tariff of PHP 120.

**Computation:** 8 × (150 - 120) = **PHP 240 tubo**

**Meaning for the study:** The system uses the word "tubo" for the amount above the tariff on fish sold. This does not represent final business profit because buying costs, transport, ice, rent, wages, and other expenses are not deducted.

For this calculation, the system uses the tariff recorded when the batch was submitted. A later tariff change does not replace that earlier reference. A batch without a recorded tariff adds zero to tubo, although its recorded sales still count. Selling below the recorded tariff produces a negative tubo amount.

### E. Remaining stock

Subtract fish sold and fish pulled out from the original available quantity.

**Formula:** Remaining stock = Original available kilograms - Kilograms sold - Kilograms pulled out

**Example:** A vendor starts with 20 kg, sells 8 kg, and pulls out 2 kg.

**Computation:** 20 - 8 - 2 = **10 kg remaining**

Expired fish is no longer counted as available. Remaining stock cannot be shown as a negative amount. The total can include confirmed batches from earlier days if they are still available for sale.

### F. Increase or decrease in a figure

Compare the current amount with the earlier amount.

**Formula:** Percentage change = [(Current amount - Previous amount) ÷ Previous amount] × 100

**Example:** Sales increased from PHP 12,000 to PHP 15,000.

**Computation:** [(15,000 - 12,000) ÷ 12,000] × 100 = **25% increase**

If the current amount is lower, the result shows a decrease. When the earlier amount is zero or below, the system shows that no comparison is available.

## 3. Vendor dashboard: the individual vendor's records

The vendor dashboard shows the signed-in vendor's own activity.

| Figure shown | How it is calculated or selected |
| --- | --- |
| Today's entries | Count all batches the vendor submitted for today, including pending, confirmed, and rejected entries |
| Confirmed entries | Count today's entries that staff have confirmed |
| Pending entries | Count today's entries still waiting for review |
| Remaining stock | Add the fish still available across the vendor's confirmed batches, including earlier deliveries |
| Sales today | Add the sales recorded today |
| Sales change | Compare today's sales with yesterday's sales using the percentage-change formula |
| Sales this month | Add sales from the first day of the current month through today |
| Tubo this month | Add the tubo on sales recorded from the first day of this month through today |
| Best sales day | Find the day with the highest positive total sales in the vendor's recorded history |
| Sales and tubo charts | Add the vendor's sales and tubo for each day, month, or year displayed |
| Today's inventory | List today's submitted batches, showing their prices, quantities, review status, and applicable freshness countdown |

**Freshness countdown:** Days left = Allowed freshness period - Days since submission. The countdown stops at zero. The allowed period follows the market's system setting.

The stock-outcome chart and selling percentage are explained in Section 6.

## 4. Staff dashboard: reviewing market submissions

The staff dashboard helps staff monitor submissions across vendors.

| Figure shown | How it is calculated or selected |
| --- | --- |
| Pending today | Count today's entries still awaiting review |
| Confirmed today | Count today's entries currently confirmed |
| Rejected today | Count today's entries currently rejected |
| Active vendors | Count vendor accounts currently marked active |
| Pending list | Show the five most recent pending entries submitted for today |
| Entries over the last seven days | Count entries for each day, separated into pending, confirmed, and rejected groups |
| Vendors without submissions | List active vendors who have no entry for today; even a pending or rejected entry counts as a submission |
| Above tariff today | Count today's pending or confirmed entries priced above their recorded tariff; use the current tariff if no earlier tariff was recorded |
| Supply this month | Add all confirmed supply from the start of this month through today |
| Supply chart | Add confirmed supply for each displayed day, month, or year |
| Vendor markup ranking | Show up to eight vendors with the highest average markup per kilogram for qualifying deliveries this month |

"Confirmed today" counts entries submitted for today that are currently confirmed. It does not count every approval action staff performed today. The seven-day chart also reflects each entry's current review status.

### Repeated submissions

When the same vendor submits the same fish and quality class more than once today, staff see those batches together.

**Example:** A vendor submits 10 kg and 15 kg of the same fish and class. Both are confirmed.

**Computation:** 10 + 15 = **25 kg confirmed supply for that group**

If another 5 kg batch is pending, the group shows **25 kg confirmed** and **5 kg pending**. Rejected quantities add to neither amount. Repeated submissions are separate deliveries and do not automatically mean that an entry is incorrect.

Staff rankings show price markups. They do not display vendors' sales or total tubo.

## 5. Supervisor dashboard: the whole market

The supervisor dashboard combines records across vendors to show market activity.

| Figure shown | How it is calculated or selected |
| --- | --- |
| Total vendors | Count vendor accounts currently marked active |
| Total stalls | Count registered stall profiles, including profiles whose vendor accounts may no longer be active |
| Active staff | Count staff accounts currently marked active |
| Today's confirmed stock | Add the original weight of confirmed deliveries submitted for today |
| Supply today / this month / this year | Add confirmed supply for today, the current month, or the current year, according to the selected filter |
| Sales today / this month / this year | Add sales recorded within the selected calendar period through today |
| Kilograms sold in the selected period | Add the quantities recorded as sold during that time |
| Tubo today / this month / this year | Add the tariff-based tubo on sales recorded during that time |
| Supply and sales charts | Show totals for each displayed day, month, or year |
| Top five suppliers | Rank vendors by the total confirmed kilograms they supplied within the selected delivery period |
| Supply by quality class | Add confirmed supply separately for each fish quality class |
| Tubo ranking | Show up to eight qualifying vendors, with the highest total tubo first |
| Biggest supply day | Find the day with the highest positive confirmed-supply total in the recorded history |
| Biggest sales day | Find the day with the highest positive sales total in the recorded history |
| Highest price recorded | Find the highest selling price per kilogram among confirmed batches |
| Highest markup recorded | Find the largest positive peso difference per kilogram between a confirmed batch's price and its recorded tariff |
| Current markup watch | Show up to five fish-and-class groups with the largest percentage markups currently available on stalls |

Today's confirmed stock measures deliveries, while remaining stock measures fish still available. For example, a confirmed 20 kg delivery remains 20 kg of supply even after the vendor sells 8 kg.

## 6. What happened to the fish?

The stock-outcome chart follows confirmed deliveries submitted from the beginning of the selected period. It shows how much of that fish has since been sold, pulled out, or expired. A related figure shows how much remains on the stall.

| Outcome | Meaning |
| --- | --- |
| Sold | Fish the vendor recorded as sold |
| Pulled out | Fish removed without being sold |
| Expired | Fish left unsold when the batch was taken off sale because of expiry |
| Still on the stall | Fish remaining available for sale |

### Selling percentage, called sell-through

The dashboard calculates the percentage of fish that was sold out of all fish that has already left the stall.

**Formula:** Selling percentage = [Kilograms sold ÷ (Kilograms sold + Kilograms pulled out + Kilograms expired)] × 100

**Example:** A batch has 8 kg sold, 2 kg pulled out, and 10 kg still available.

**Computation:** [8 ÷ (8 + 2)] × 100 = **80%**

The 10 kg still available is excluded because it has not yet left the stall. If that 10 kg later expires:

**New computation:** [8 ÷ (8 + 2 + 10)] × 100 = **40%**

If no fish has left the stall, the system shows a dash because there is no selling percentage to calculate yet.

For a circular chart, each slice's percentage is its quantity divided by the total quantity shown in that chart, multiplied by 100. The same method applies to the supervisor's supply-by-class chart.

## 7. How vendor markup rankings work

For average markup rankings, the system uses this month's confirmed deliveries with a recorded tariff greater than zero. Larger deliveries have more influence on the average because they represent more fish.

**Example:** A vendor has two confirmed deliveries:

| Delivery | Quantity | Markup per kilogram | Markup percentage |
| --- | --- | --- | --- |
| First delivery | 10 kg | PHP 30 | 25% |
| Second delivery | 30 kg | PHP 10 | 10% |

**Formula:** Average markup = Total of (Each delivery's markup × Its quantity) ÷ Total quantity

**Computation:** [(30 × 10) + (10 × 30)] ÷ (10 + 30) = **PHP 15 average markup per kilogram**

The average percentage is calculated separately using each delivery's percentage and quantity:

**Computation:** [(25 × 10) + (10 × 30)] ÷ 40 = **13.8%**, rounded to one decimal place.

The staff ranking places the highest average peso markup first. If averages are equal, the larger single-batch markup comes first. The supervisor ranking places the highest total tubo first, then uses average peso markup when tubo amounts are equal.

The supervisor's ranking can include tubo from older deliveries sold this month, but a vendor must also have a qualifying confirmed delivery this month to appear. As a result, adding the displayed ranking rows may not equal the whole market's monthly tubo.

## 8. How to read the dates and charts

### This month compared with last month

"This month" means the first day of the current month through today.

**Example:** On November 14, the monthly figure covers November 1-14. Its comparison covers October 1-14.

If the previous month has fewer days, the comparison stops at that month's last day. For example, March 1-31 is compared with February 1-28, or February 1-29 in a leap year.

### Chart periods

| Selected view | Dates shown | Meaning of each point or bar |
| --- | --- | --- |
| Daily | Today and the previous 29 days | Total for one day |
| Monthly | This month and the previous 11 months | Total for one month |
| Yearly | This year and the previous 4 years | Total for one year |

A period with no recorded activity appears as zero. The current month or year is still ongoing, so its total may be lower simply because it is not finished.

All dashboards offer Daily, Monthly, and Yearly filters. On the supervisor dashboard, these also switch the supply, sales, and tubo cards to today, this month, and this year respectively. Comparisons use yesterday, the same days last month, or the same dates last year. The chart caption describes its wider historical window.

Today's stock, account counts, the explicitly monthly tubo leaderboard, current markup watch, and all-time records keep their stated meanings when the supervisor changes the filter. On the staff and vendor dashboards, the period filter changes the charts and their related breakdowns.

### Delivery date and sale date answer different questions

**Example:** A vendor submits 20 kg yesterday and sells 8 kg today at PHP 150 per kilogram.

- Yesterday's supply includes the 20 kg delivery.
- Today's sales include 8 × 150 = PHP 1,200.
- The stock-outcome chart follows what happened to that delivery, including sales recorded after its submission date.

Therefore, today's supply and today's sales do not necessarily refer to the same deliveries.

## 9. Understanding the consumer tariff comparison

The consumer tariff display compares fish and quality classes currently available for sale.

| Figure | Meaning |
| --- | --- |
| Official tariff | The current reference price per kilogram |
| Lowest price | The lowest price among available batches of that fish and class |
| Highest price | The highest price among available batches of that fish and class |
| Stalls selling | The number of different vendors offering that fish and class |
| Highest markup | The stall with the highest price above the current tariff for that fish and class |

If one stall has several available batches of the same fish and class, its highest batch price is used to represent that stall in the markup comparison. Any overall average price prepared by the system is the total of available batch prices divided by the number of batches; batch quantities do not affect that average.

Current price comparisons use today's active tariff. Historical tubo uses the tariff recorded with the delivery, so the two can differ after a tariff change.

## 10. Points to explain during the research presentation

1. **The figures come from recorded activity.** Supply depends on confirmed submissions, and sales depend on what vendors record as sold. The dashboard does not independently observe every purchase.
2. **Supply, sales, and remaining stock have different meanings.** Supply measures deliveries, sales measures the value sold, and remaining stock measures what is still available.
3. **Tubo is measured against the tariff.** It does not deduct business expenses and should not be presented as net profit.
4. **The selling percentage covers fish that has left the stall.** It excludes fish still available for sale.
5. **Historical figures can be updated.** Corrections, approvals, and later recorded sales can change what the dashboard shows. Older market records remain relevant even if an account later becomes inactive.
6. **Displayed values are rounded.** Money usually appears to two decimal places and percentages to one. Small differences can occur when adding rounded screen figures.
7. **Use correctly dated records.** Some rankings and stock breakdowns include records from the selected starting date onward. Future-dated entries could make them differ from charts that stop at today.

### Suggested explanation to the panel

> The dashboards summarize the records submitted by vendors and reviewed by market staff. Supply is the total confirmed weight of fish delivered. Sales are calculated by multiplying the quantity recorded as sold by its selling price. Remaining stock is the original available quantity less fish sold and removed. The system also compares selling prices with the official tariff. It calls the price difference on quantities sold "tubo," which is a tariff-based measure and does not deduct business expenses. Vendors see their own records, staff monitor submissions and prices, and supervisors review totals across the market.
