<section id="reports" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">07 · From daily records to summaries</div>
    <h2>Dashboards &amp; reports</h2>
    <p class="calc-lead">A correct formula also needs the correct filter and date. These definitions explain why two screens can show different totals without using different arithmetic.</p>
    <h3>Totals and change percentages</h3>
    <div class="calc-equation">Period total = sum of eligible daily values in that period<br>Change % = (current − previous) / previous × 100<br>Example: (1,200 − 1,000) / 1,000 × 100 = <strong>+20.0%</strong></div>
    <p>Dashboard change percentages are rounded to 1 decimal. If the previous total is zero or negative, the change percentage is unavailable.
        The system does not divide by zero or label a new positive total as “100% growth.”</p>
    <p class="calc-small">The vendor’s daily sales change compares today with yesterday. The shared delta badge shows the absolute percentage with its direction arrow;
        zero change uses the nonnegative badge. Chart labels may abbreviate 1,000 as “1k” or 1,000,000 as “1M”; the underlying totals retain their units and precision.</p>
    <h3>Month-to-date compares the same part of the month</h3>
    <p>Compare the current month’s first day through today with the previous month’s first day through the matching day number.
        If the previous month is shorter, stop at its final day. On March 31, for example, compare March 1–31 with February 1–28, or February 1–29 in a leap year.</p>
    <div class="calc-table-wrap"><table class="calc-table">
        <caption>Dashboard chart periods</caption>
        <thead><tr><th scope="col">Toggle</th><th scope="col">Window</th><th scope="col">Calculation</th></tr></thead>
        <tbody><tr><td>Daily</td><td>Last 30 days, including today</td><td>One sum per calendar day.</td></tr><tr><td>Monthly</td><td>Current month + 11 preceding months</td><td>Sum daily values by year and month.</td></tr><tr><td>Quarterly</td><td>Current quarter + 7 preceding quarters</td><td>Q1: Jan–Mar; Q2: Apr–Jun; Q3: Jul–Sep; Q4: Oct–Dec.</td></tr><tr><td>Yearly</td><td>Current year + 4 preceding years</td><td>Sum daily values by calendar year.</td></tr></tbody>
    </table></div>
    <p class="calc-small">Chart buckets without events are 0. Monthly, quarterly and yearly totals are sums of daily quantities or values; they are not averages of the chart’s bars.
        The current bucket can be incomplete because the day, month, quarter or year is still in progress.</p>
    <h3>What the dashboard cards and rankings count</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Figure</th><th scope="col">Definition</th></tr></thead>
        <tbody>
            <tr><td>Supply; today’s confirmed stock</td><td>Sum original <code>stock_kg</code> of confirmed batches by entry date. Today’s incoming total is different from all fish still available now.</td></tr>
            <tr><td>Sales / sold kg / tubo cards</td><td>Aggregate sold releases by their recording date. Market-wide on the supervisor dashboard; limited to that vendor on the vendor dashboard. Staff dashboards show supply and markup without private sales totals.</td></tr>
            <tr><td>Stock outcome</td><td>Select confirmed batches by their entry date within the window, then inspect their cumulative sold, pulled-out, expired and remaining amounts. This is a batch cohort, not just releases made during the window.</td></tr>
            <tr><td>Top suppliers</td><td>Group confirmed incoming kg by vendor in the selected window, sort greatest to least, and show the requested leading vendors.</td></tr>
            <tr><td>Supply by class</td><td>Sum confirmed incoming kg by quality class. Any percentage in a class/outcome chart is category kg ÷ that chart’s total kg × 100; it is not another forecast.</td></tr>
            <tr><td>Best supply / sales day</td><td>The highest positive daily supply or sales total in the available daily history. No positive day means no record.</td></tr>
            <tr><td>Highest recorded price / markup</td><td>Maximum confirmed batch price, or maximum positive batch price minus saved tariff. These are historical records, not averages.</td></tr>
            <tr><td>Active vendors / staff</td><td>Count user accounts in that role whose status is active. Stall totals count vendor profiles; these are different from stalls currently selling on the public board.</td></tr>
            <tr><td>Today’s confirmed / pending / rejected</td><td>Count batches with today’s entry date and the matching review status. The staff seven-day chart uses one such count per day.</td></tr>
            <tr><td>Vendors with no submission today</td><td>Active vendor accounts with no batch entered today, regardless of the status a submission would have had.</td></tr>
            <tr><td>Repeated submissions</td><td>Group today’s entries by vendor + fish + quality, retain groups with more than one batch, and separately sum confirmed and pending incoming kg.</td></tr>
            <tr><td>Above-tariff review count</td><td>Count today’s pending or confirmed batches above their saved tariff, falling back to the current tariff when no snapshot exists. Rejected entries are excluded.</td></tr>
        </tbody>
    </table></div>
    <details class="calc-details"><summary>Public board statistics and batch numbering</summary><div class="calc-details-body">
        <ul class="calc-list">
            <li><strong>Live vendors:</strong> distinct vendor cards with open stock.</li>
            <li><strong>Listings:</strong> count vendor + fish + class groups, not the number of underlying batches.</li>
            <li><strong>Varieties:</strong> distinct fish type IDs represented by open batches.</li>
            <li><strong>Available kg:</strong> sum remaining kg over the displayed open batches.</li>
            <li><strong>Above tariff:</strong> count vendor listings whose highest open price is above the current tariff.</li>
            <li><strong>Next batch number:</strong> 1 plus the highest number for the same vendor, fish and class among today’s batches, any pending batches and older open batches. After those older batches are gone, a new day can restart at Batch 1.</li>
        </ul>
    </div></details>
    <h3>Supply reports and PDF</h3>
    <p>Staff reports and their PDF use the same report service. They select confirmed batches by entry date, count batches and distinct vendors/fish types,
        total incoming stock, and show minimum, maximum and <strong>quantity-weighted average price</strong>.</p>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Report period</th><th scope="col">Selected dates</th><th scope="col">Breakdown groups</th></tr></thead>
        <tbody><tr><td>Daily</td><td>One selected calendar date</td><td>Vendor</td></tr><tr><td>Monthly</td><td>First to last day of the selected month</td><td>Day</td></tr><tr><td>Yearly</td><td>January 1 to December 31 of the selected year</td><td>Month</td></tr></tbody>
    </table></div>
    <p class="calc-small">A separate pull-out section lists unsold releases by when they were recorded, even if their batch arrived earlier.
        These reports do not treat pull-outs as sales. Distinct vendors in the whole period are counted once, so adding subgroup vendor counts can double-count a vendor.</p>
    <details class="calc-details"><summary>Supervisor summaries, CSV history and forecast summary cards</summary><div class="calc-details-body">
        <p><strong>Supervisor daily vendor summaries:</strong> confirmed batches on the selected entry date; incoming kg, batch count, simple average batch price,
            simple average saved-tariff markup, batches above tariff and distinct fish names. The market total adds the vendors’ incoming kg, and active-in-report vendors counts vendors with entries.</p>
        <p><strong>CSV history:</strong> one row per entry date + fish + quality, containing batch count, distinct vendors, original supply,
            cumulative batch sold/pulled-out kg, simple average/min/max batch price, average saved tariff, average price minus average tariff,
            and Σ(batch sold kg × batch price). A missing tariff remains blank.</p>
        <div class="calc-note"><strong>Date basis of the CSV:</strong> its sold and sales-value columns are attributed to the batch’s entry date.
            Sales charts instead use the sale release’s recording date. A sale made days after arrival can therefore fall on a different day in the two outputs.</div>
        <p><strong>Forecast summary cards:</strong> average is the mean of the rounded predicted values across the horizon; highest and lowest are the greatest and least predicted values, not the range boundaries.
            Ties select the earliest step. The forecast is marked flat when the high-minus-low difference is less than 0.005.</p>
        <p class="calc-small">The forecast’s tariff reference line is a comparison only; it does not force the model to predict at the tariff.</p>
    </div></details>
</section>
