{{--
    Add Stock trigger for one inventory line. Pair with
    vendor.partials.add-stock-modal on the same page.

    Rendered only when the line can actually take more stock; otherwise the
    reason from VendorInventory::addStockBlocker() is left to the caller.
--}}
<button type="button"
        data-add-stock
        data-id="{{ $entry->id }}"
        data-url="{{ route('vendor.inventory.add-stock', $entry) }}"
        data-fish="{{ $entry->fishType?->name ?? 'Fish' }}"
        data-quality="{{ $entry->quality_class }}"
        data-session="{{ $entry->session() }}"
        data-price="{{ number_format((float) $entry->price_per_kg, 2) }}"
        data-stock="{{ (float) $entry->stock_kg }}"
        onclick="openAddStock(this)"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold transition-colors text-[10.5px] bg-success-50 text-success-700 border border-success-200 hover:bg-success-100">
    <x-icon name="bi-plus-lg" size="2xs" /> Add Stock
</button>
