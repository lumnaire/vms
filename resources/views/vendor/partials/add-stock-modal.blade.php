{{--
    Add Stock dialog, shared by My Inventory and the vendor dashboard.

    Opened by any button rendered from vendor.partials.add-stock-button. More fish
    of a confirmed line goes live straight away at the price staff already agreed,
    so there is no second approval:

      - same session as the line  → the line is topped up
      - the other session         → a new confirmed line opens for that session

    The dialog says which of the two will happen before the vendor submits, so a
    PM top-up is never a surprise relabelling of the morning's fish.

    When the server refuses the top-up, the dialog reopens on the same line with
    the vendor's numbers still in it (keyed by the hidden add_stock_entry field).
--}}

@php
    $addStockErrors = old('add_stock_entry')
        ? collect(['stock', 'stock_kg', 'released_kg', 'market_session'])
            ->map(fn ($key) => $errors->first($key))
            ->filter()
            ->values()
        : collect();
@endphp

<div id="addStockModal"
     class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="addStockTitle"
     style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px)"
     onclick="if (event.target === this) closeAddStock()">

    <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-modal">

        <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50">
                    <x-icon name="bi-box-arrow-in-down" size="lg" class="text-success-600" />
                </div>
                <div class="min-w-0">
                    <h3 id="addStockTitle" class="text-slate-800 font-bold text-[15px]">Add Stock</h3>
                    <p id="addStockSubtitle" class="text-slate-400 text-[11.5px] mt-0.5 truncate"></p>
                </div>
            </div>
            <button type="button" onclick="closeAddStock()" aria-label="Close"
                    class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-slate-100 transition-colors text-slate-400">
                <x-icon name="bi-x-lg" size="sm" />
            </button>
        </div>

        <form id="addStockForm" method="POST" class="px-5 py-5 space-y-4">
            @csrf
            <input type="hidden" name="add_stock_entry" id="addStockEntry">

            @if($addStockErrors->isNotEmpty())
                <div class="rounded-lg px-3 py-2.5 text-[12px] bg-danger-50 border border-danger-200 text-danger-800">
                    @foreach($addStockErrors as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg px-3 py-2.5 text-[11.5px] leading-[1.5] bg-success-50 text-success-800 border border-success-200">
                <x-icon name="bi-lightning-charge-fill" size="xs" class="mr-1" />
                Goes on the price board <strong>immediately</strong> at
                <strong id="addStockPrice"></strong>/kg, the price staff already confirmed. No second review.
            </div>

            <div>
                <label class="vpm-label-text" for="addStockSession">Trading Session <span class="text-danger-500">*</span></label>
                <select name="market_session" id="addStockSession" class="vpm-select" required onchange="describeAddStock()">
                    @foreach(\App\Models\VendorInventory::SESSIONS as $session)
                        <option value="{{ $session }}">{{ $session }} — {{ $session === 'AM' ? 'Morning' : 'Afternoon' }}</option>
                    @endforeach
                </select>
                <p id="addStockSessionHint" class="mt-1 text-slate-500 text-[11px]"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="vpm-label-text" for="addStockKg">Stock Added (kg) <span class="text-danger-500">*</span></label>
                    <input type="number" name="stock_kg" id="addStockKg" class="vpm-input"
                           step="0.1" min="0.1" max="99999.99" placeholder="0.0" required
                           oninput="syncAddStockReleased()">
                </div>
                <div>
                    <label class="vpm-label-text" for="addStockReleased">For Sale (kg) <span class="text-danger-500">*</span></label>
                    <input type="number" name="released_kg" id="addStockReleased" class="vpm-input"
                           step="0.1" min="0.1" max="99999.99" placeholder="0.0" required>
                </div>
            </div>
            <p class="-mt-2 text-slate-400 text-[11px]">For sale cannot be more than the stock you are adding.</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" onclick="closeAddStock()" class="vpm-btn vpm-btn-secondary vpm-btn-sm">Cancel</button>
                <button type="submit" class="vpm-btn vpm-btn-success vpm-btn-sm">
                    <x-icon name="bi-plus-lg" size="xs" /> Add Stock
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('addStockModal');
        let current = null;

        function fmtKg(n) {
            return Number(n).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 2 }) + ' kg';
        }

        window.openAddStock = function (btn, restore) {
            current = btn.dataset;

            document.getElementById('addStockForm').action = current.url;
            document.getElementById('addStockEntry').value = current.id;
            document.getElementById('addStockSubtitle').textContent =
                current.fish + ' · ' + current.quality + ' · ' + current.session + ' line';
            document.getElementById('addStockPrice').textContent = '₱' + current.price;

            document.getElementById('addStockSession').value = restore?.session || current.session;
            document.getElementById('addStockKg').value = restore?.stock || '';
            document.getElementById('addStockReleased').value = restore?.released || '';

            describeAddStock();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => document.getElementById('addStockKg').focus(), 50);
        };

        window.closeAddStock = function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        // Says, before submitting, whether this tops the line up or opens a new one.
        window.describeAddStock = function () {
            if (!current) return;
            const picked = document.getElementById('addStockSession').value;
            const hint = document.getElementById('addStockSessionHint');

            hint.textContent = picked === current.session
                ? 'Tops up this ' + picked + ' line (now ' + fmtKg(current.stock) + ').'
                : 'Opens a separate ' + picked + ' line. Your ' + current.session + ' line stays as it is.';
        };

        window.syncAddStockReleased = function () {
            const stock = document.getElementById('addStockKg').value;
            const released = document.getElementById('addStockReleased');
            if (!released.value || parseFloat(released.value) > parseFloat(stock)) {
                released.value = stock;
            }
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeAddStock();
        });

        @if(old('add_stock_entry'))
            // The server refused the last top-up: reopen it on the same line.
            const failed = document.querySelector('[data-add-stock][data-id="{{ (int) old('add_stock_entry') }}"]');
            if (failed) {
                openAddStock(failed, {
                    session:  @json(old('market_session')),
                    stock:    @json(old('stock_kg')),
                    released: @json(old('released_kg')),
                });
            }
        @endif
    })();
</script>
@endpush
