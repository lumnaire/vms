{{--
    One batch in the vendor's inventory tables.

    @param VendorInventory $entry
    @param bool            $closed  Closed-batches table: shows Sold instead of
                                    Remaining and has no ⋮ menu.
--}}
@php
    $closed    = $closed ?? false;
    $state     = $entry->getStockState();
    $remaining = $entry->getRemainingStock();
    $label     = ($entry->fishType?->name ?? 'Fish') . ' · ' . $entry->batchLabel();

    $stateText = [
        'pending'     => ['bi-clock', 'Pending'],
        'rejected'    => ['bi-x-circle-fill', 'Rejected'],
        'on_sale'     => ['bi-check-circle-fill', 'On sale'],
        'sold_out'    => ['bi-bag-check-fill', 'Sold out'],
        'expired'     => ['bi-trash3', 'Expired'],
    ][$state];

    // Batches waiting for staff or on sale count down to automatic removal.
    $counting = $entry->isCountingDown();
@endphp
<tr class="batch-row">
    <td class="px-4 py-3">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 bg-brand-50">
                <x-icon name="bi-water" size="sm" class="text-blue-500" />
            </div>
            <div class="min-w-0">
                <p class="text-slate-700 font-semibold text-[13px] truncate">{{ $entry->fishType?->name ?? 'Unknown' }}</p>
                <p class="text-slate-400 text-[11px]">
                    {{ $entry->batchLabel() }} &middot;
                    {{ $entry->entry_date->isToday() ? 'Today' : $entry->entry_date->format('M j') }}
                </p>
            </div>
        </div>
    </td>
    <td class="px-4 py-3 text-[12px] text-slate-500">{{ $entry->quality_class }}</td>
    <td class="px-4 py-3 text-right text-[13px] font-semibold text-slate-700">₱{{ number_format((float) $entry->price_per_kg, 2) }}</td>
    <td class="px-4 py-3 text-right text-[12.5px] text-slate-600">{{ number_format((float) $entry->stock_kg, 1) }} kg</td>
    <td class="px-4 py-3 text-right text-[12.5px] font-semibold text-slate-800">
        @if($closed)
            {{ number_format($entry->getSoldKg(), 1) }} kg
        @elseif($entry->isConfirmed())
            {{ number_format($remaining, 1) }} kg
        @else
            <span class="text-slate-300 font-normal">&mdash;</span>
        @endif
    </td>
    <td class="px-4 py-3 text-center">
        @if($counting)
            <span class="days-pill {{ $entry->getDaysLeft() <= 1 ? 'is-last' : '' }}"
                  title="Removed automatically on {{ $entry->expiresOn()->format('M j') }} if not sold out.">{{ $entry->countdownLabel() }}</span>
        @else
            <span class="text-slate-300">&mdash;</span>
        @endif
    </td>
    <td class="px-4 py-3 text-center">
        <span class="state-pill state-{{ $state }}">
            <x-icon name="{{ $stateText[0] }}" size="2xs" /> {{ $stateText[1] }}
        </span>
    </td>
    <td class="px-3 py-3 text-center">
        @unless($closed || $entry->isRejected())
            <button type="button" class="kebab-btn" aria-haspopup="menu" aria-expanded="false"
                    aria-label="Actions for {{ $label }}"
                    onclick="openBatchMenu(this)"
                    data-id="{{ $entry->id }}"
                    data-label="{{ $label }}"
                    data-sold="{{ $entry->getSoldKg() }}"
                    data-remaining="{{ $entry->isConfirmed() ? $remaining : (float) $entry->stock_kg }}"
                    data-can-release="{{ $entry->canRelease() ? '1' : '0' }}"
                    data-release-url="{{ route('vendor.inventory.release', $entry) }}"
                    data-cancel-url="{{ $entry->isPending() ? route('vendor.inventory.destroy', $entry) : '' }}">
                <x-icon name="bi-three-dots-vertical" size="sm" />
            </button>
        @endunless
    </td>
</tr>
