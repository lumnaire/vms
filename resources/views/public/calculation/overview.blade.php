<section id="overview" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">01 · The big picture</div>
    <h2>A batch becomes information.</h2>
    <p class="calc-lead">One batch is one vendor’s submission for one fish type and quality class.
        Its price and incoming kilograms describe the supply. Later sales and pull-outs describe what happened to that stock.</p>
    <div class="calc-flow">
        <div><span>01 / RECORD</span><strong>A vendor submits a batch</strong><p>Fish, quality, price per kilogram and incoming stock.</p></div>
        <div><span>02 / CONFIRM</span><strong>Staff verify the entry</strong><p>Confirmed batches feed supply totals and forecasting history.</p></div>
        <div><span>03 / MONITOR</span><strong>Stock changes over time</strong><p>Sold fish, unsold pull-outs and expiry reduce what remains.</p></div>
        <div><span>04 / EXPLAIN</span><strong>Reports &amp; forecasts</strong><p>Daily history becomes summaries, comparisons and future estimates.</p></div>
    </div>
    <div class="calc-grid calc-grid-three">
        <article class="calc-card"><x-icon name="bi-box-seam" class="calc-card-icon" /><h3>Supply is an arrival</h3><p class="calc-small">The kilograms originally brought in, counted on the batch’s entry date. Selling that fish does not erase its historical supply.</p></article>
        <article class="calc-card"><x-icon name="bi-basket" class="calc-card-icon" /><h3>Stock is a balance</h3><p class="calc-small">The fish still available now. It can include yesterday’s batch if that batch is still confirmed, unexpired and unsold.</p></article>
        <article class="calc-card"><x-icon name="bi-cash-stack" class="calc-card-icon" /><h3>Sales are an event</h3><p class="calc-small">A vendor records kilograms as sold. The system values them at that batch’s price and dates them when the sale is recorded.</p></article>
    </div>
    <div class="calc-note"><strong>About the examples.</strong> All numbers on this page are illustrative, including the editable forecast.
        They do not expose a vendor’s private sales or change inventory. The forecasting working is calculated by the production ARIMA service.</div>
</section>

<section id="stock-supply" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">02 · Kilograms in, kilograms left</div>
    <h2>Stock &amp; supply</h2>
    <p class="calc-lead">Keep the incoming quantity, the remaining quantity and the sold quantity separate. They answer different questions.</p>
    <h3>Daily supply and daily average price</h3>
    <p>For forecasting, entries are grouped by <strong>fish type + quality class + entry date</strong>.
        Use confirmed batches only. A pending or rejected entry is not confirmed supply.</p>
    <div class="calc-table-wrap"><table class="calc-table">
        <caption>Example: three confirmed batches of the same fish and class, on the same day</caption>
        <thead><tr><th scope="col">Vendor</th><th scope="col" class="calc-num">Incoming stock Q</th><th scope="col" class="calc-num">Price P</th></tr></thead>
        <tbody><tr><td>A</td><td class="calc-num">12 kg</td><td class="calc-num">₱180/kg</td></tr><tr><td>B</td><td class="calc-num">8 kg</td><td class="calc-num">₱190/kg</td></tr><tr><td>C</td><td class="calc-num">5 kg</td><td class="calc-num">₱200/kg</td></tr></tbody>
    </table></div>
    <div class="calc-grid">
        <article class="calc-card"><h3>Supply: add quantities</h3><div class="calc-equation">Supply = ΣQ<br>12 + 8 + 5 = <strong>25 kg</strong></div><p class="calc-small">Each additional confirmed batch adds its incoming kilograms once. Existing fish left overnight is not a new arrival.</p></article>
        <article class="calc-card"><h3>Forecast price: average batch prices</h3><div class="calc-equation">Daily price = ΣP / batch count<br>(180 + 190 + 200) / 3 = <strong>₱190/kg</strong></div><p class="calc-small">This is an unweighted mean: each batch price counts equally, even when batch sizes differ. It is not an average per distinct vendor.</p></article>
    </div>
    <h3>Remaining stock and its value</h3>
    <p>Take a separate example: a confirmed <strong>20 kg</strong> batch priced at <strong>₱200/kg</strong>.
        The vendor sells <strong>12 kg</strong> and pulls out <strong>3 kg</strong> without selling it.</p>
    <div class="calc-equation">Remaining = max(0, initial available − sold − pulled out)<br>max(0, 20 − 12 − 3) = <strong>5 kg</strong><br>Remaining stock value = 5 × ₱200 = <strong>₱1,000</strong></div>
    <p class="calc-small">The stored <code>released_kg</code> field means the initial quantity made available; it is set to <code>stock_kg</code> on submission.
        Individual releases are recorded separately. The remaining stock value is the asking-price value of unsold fish; it is not received revenue.</p>
    <div class="calc-grid">
        <article class="calc-card"><h3>When is a batch on the public board?</h3><p class="calc-small">It must be confirmed, have kilograms left, and have no expiry/disposal or legacy carry-out marker. Pending and rejected batches are excluded. A sold-out or expired batch disappears from the current board but confirmed history remains available for reports and forecasts.</p></article>
        <article class="calc-card"><h3>What happens when it expires?</h3><p class="calc-small">Remaining saleable stock becomes 0. In the 20 kg example, the 5 kg left is classified as expired; it is not added to sales or ordinary pull-outs. The original 20 kg remains historical supply. Old pending entries are deleted by the expiry process.</p></article>
    </div>
    <h3>Stock outcome and sell-through</h3>
    <div class="calc-equation">Initial available = sold + pulled out + expired + still on stall<br>Departed stock = sold + pulled out + expired<br>Sell-through = sold / departed stock × 100</div>
    <p>Before the example batch expires, sell-through is <strong>12 / (12 + 3) × 100 = 80%</strong>.
        After its final 5 kg expires, sell-through is <strong>12 / (12 + 3 + 5) × 100 = 60%</strong>.</p>
    <div class="calc-note"><strong>The denominator matters.</strong> The dashboard measures the sold share of stock that has left the stall.
        It excludes stock still on sale. Sold ÷ initial supply is a different ratio. If nothing has left, the dashboard has no sell-through percentage.</div>
    <h3>Freshness countdown</h3>
    <div class="calc-equation">Age = calendar days from entry date to today<br>Days left = max(0, {{ $settings['freshness'] }} − age)<br>Expire when age ≥ {{ $settings['freshness'] }} days and the batch is pending or still open</div>
    <p class="calc-small">The active freshness limit is <strong>{{ $settings['freshness'] }} days</strong>, counted using entry dates rather than an exact hour-by-hour shelf life.
        A batch submitted today starts with {{ $settings['freshness'] }} days left. Expiry is checked by the scheduled job and before web pages are shown.</p>
</section>
