{{--
    The tariff table, laid out like the municipal tariff list: local name, price
    per kilo, English name. Beside each tariff it shows what vendors are charging
    on the board right now and the stall with the highest markup.

    Expects $groups (class => fish types), $board (MarketAnalytics::boardByFish)
    and $editable (supervisor: inputs named tariffs[id] / english[id], attached
    to the page's #tariffForm through the form attribute so the modal's own
    form is never nested inside it).
--}}
@push('styles')
<style>
    @layer components {
        .tariff-table .tariff-group-row td {
            background-color: var(--color-surface-subtle);
            border-top: 1px solid var(--color-slate-200);
            border-bottom: 1px solid var(--color-slate-200);
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .tariff-table .tariff-input { padding: 6px 10px; font-size: 12.5px; }
        .tariff-table .tariff-input-price { width: 7.5rem; text-align: right; }
        .tariff-table .tariff-input-text { min-width: 9rem; }
        .tariff-table .tariff-input.is-changed {
            border-color: var(--color-warning-400);
            background-color: var(--color-warning-50);
        }
        /* No display here: the .hidden / .flex utilities on the markup decide. */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 1000;
            align-items: center; justify-content: center; padding: 16px;
            background-color: rgb(15 23 42 / 0.55);
        }
    }
</style>
@endpush
<div class="vpm-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="vpm-table tariff-table" id="tariffTable">
            <thead>
                <tr>
                    <th>Local / Commercial Name</th>
                    <th>English Name</th>
                    <th class="vpm-th-right">Tariff (₱/kg)</th>
                    <th>Effective</th>
                    <th class="vpm-th-right">On the Board Now</th>
                    <th>Highest Markup</th>
                    <th class="vpm-th-center">History</th>
                    @if($editable)
                        <th class="vpm-th-center">Fish</th>
                    @endif
                </tr>
            </thead>
            @foreach($groups as $class => $fishList)
                <tbody class="tariff-group">
                    <tr class="tariff-group-row">
                        <td colspan="{{ $editable ? 8 : 7 }}">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <x-quality-badge :quality="$class" />
                                    <span class="vpm-meta ml-2">
                                        {{ $fishList->filter(fn($f) => $f->currentTariff)->count() }} of {{ $fishList->count() }} set
                                    </span>
                                </div>
                                @if($editable)
                                    <button type="button" class="vpm-btn vpm-btn-ghost vpm-btn-sm"
                                            onclick="openFishModal('add', { quality_class: @js($class) })">
                                        <x-icon name="bi-plus-lg" size="xs" /> Add to {{ $class }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @foreach($fishList as $fish)
                        @php
                            $tariff  = $fish->currentTariff;
                            $live    = $board->get(App\Models\PriceGuide::key($fish->id, $fish->quality_class));
                            $tariffVal = old("tariffs.{$fish->id}", $tariff ? number_format((float) $tariff->tariff_price, 2, '.', '') : '');
                            $englishVal = old("english.{$fish->id}", $fish->english_name);
                        @endphp
                        <tr class="tariff-row" data-search="{{ strtolower($fish->name . ' ' . $fish->english_name) }}">
                            <td class="vpm-cell-strong">{{ $fish->name }}</td>

                            <td>
                                @if($editable)
                                    <input type="text" form="tariffForm" name="english[{{ $fish->id }}]" value="{{ $englishVal }}"
                                           data-original="{{ $fish->english_name }}" maxlength="100"
                                           placeholder="e.g. Milkfish" aria-label="English name of {{ $fish->name }}"
                                           class="vpm-input tariff-input tariff-input-text {{ $errors->has("english.{$fish->id}") ? 'is-invalid' : '' }}">
                                @else
                                    <span class="{{ $fish->english_name ? 'text-slate-600' : 'text-slate-300' }}">{{ $fish->english_name ?: '—' }}</span>
                                @endif
                            </td>

                            <td class="vpm-td-right">
                                @if($editable)
                                    <input type="number" form="tariffForm" name="tariffs[{{ $fish->id }}]" value="{{ $tariffVal }}"
                                           data-original="{{ $tariff ? number_format((float) $tariff->tariff_price, 2, '.', '') : '' }}"
                                           step="0.01" min="0.01" max="99999.99" inputmode="decimal"
                                           placeholder="Not set" aria-label="Tariff of {{ $fish->name }} per kilo"
                                           class="vpm-input vpm-num tariff-input tariff-input-price {{ $errors->has("tariffs.{$fish->id}") ? 'is-invalid' : '' }}">
                                    @error("tariffs.{$fish->id}")
                                        <p class="text-rose-600 text-[10.5px] mt-1">{{ $message }}</p>
                                    @enderror
                                @elseif($tariff)
                                    <span class="vpm-num font-bold text-slate-800">₱{{ number_format((float) $tariff->tariff_price, 2) }}</span>
                                @else
                                    <span class="text-slate-300 text-[11.5px]">Not set</span>
                                @endif
                            </td>

                            <td>
                                @if($tariff)
                                    <p class="text-slate-600 text-[12px]">{{ $tariff->effective_date->format('M j, Y') }}</p>
                                    <p class="vpm-meta">{{ $tariff->setBy?->name ? 'by ' . $tariff->setBy->name : '' }}</p>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            <td class="vpm-td-right">
                                @if($live)
                                    <p class="vpm-num font-semibold text-slate-700">
                                        ₱{{ number_format($live['min'], 2) }}@if($live['max'] > $live['min']) – ₱{{ number_format($live['max'], 2) }}@endif
                                    </p>
                                    <p class="vpm-meta">{{ $live['stalls']->count() }} {{ Str::plural('stall', $live['stalls']->count()) }} · avg ₱{{ number_format($live['avg'], 2) }}</p>
                                @else
                                    <span class="text-slate-300 text-[11.5px]">Not on sale</span>
                                @endif
                            </td>

                            <td>
                                @if($live && $live['top_markup'])
                                    @php $top = $live['top_markup']; @endphp
                                    <div class="flex items-center gap-2">
                                        <x-tariff-diff :comparison="$top['comparison']" align="start" />
                                        <span class="text-slate-600 text-[11.5px] whitespace-nowrap">Stall {{ $top['stall'] }}</span>
                                        @if($top['note'])
                                            <span class="text-slate-400 cursor-help" title="Vendor's reason: {{ $top['note'] }}">
                                                <x-icon name="bi-chat-left-text" size="xs" />
                                            </span>
                                        @endif
                                    </div>
                                @elseif($live && $live['tariff'] !== null)
                                    <x-badge variant="success">All at or below</x-badge>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            <td class="vpm-td-center">
                                @if($fish->tariffHistory->isNotEmpty())
                                    <button type="button" class="vpm-btn vpm-btn-ghost vpm-btn-sm"
                                            onclick="openTariffHistory({{ $fish->id }})"
                                            title="Tariff history of {{ $fish->name }}">
                                        <x-icon name="bi-clock-history" size="xs" /> {{ $fish->tariffHistory->count() }}
                                    </button>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            @if($editable)
                                <td class="vpm-td-center whitespace-nowrap">
                                    <button type="button" class="vpm-icon-btn" title="Edit {{ $fish->name }}" aria-label="Edit {{ $fish->name }}"
                                            onclick="openFishModal('edit', @js(['id' => $fish->id, 'name' => $fish->name, 'english_name' => $fish->english_name, 'quality_class' => $fish->quality_class, 'url' => route('supervisor.price-guides.fish.update', $fish)]))">
                                        <x-icon name="bi-pencil" size="xs" />
                                    </button>
                                    <button type="button" class="vpm-icon-btn vpm-icon-btn-danger" title="Remove {{ $fish->name }}" aria-label="Remove {{ $fish->name }}"
                                            onclick="openRemoveFish(@js(['name' => $fish->name, 'url' => route('supervisor.price-guides.fish.destroy', $fish), 'has_history' => $fish->vendor_inventories_count > 0]))">
                                        <x-icon name="bi-trash" size="xs" />
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>
    <div id="tariffNoMatch" class="hidden text-center py-10">
        <p class="text-slate-500 font-semibold text-[13px]">No fish match your search</p>
    </div>
</div>

{{-- ── Tariff history modal ── --}}
<div id="tariffHistoryModal" class="modal-overlay hidden flex" onclick="if (event.target === this) closeTariffHistory()">
    <div class="vpm-modal" role="dialog" aria-modal="true" aria-labelledby="tariffHistoryTitle">
        <div class="vpm-modal-header">
            <div>
                <p class="vpm-modal-title" id="tariffHistoryTitle">Tariff history</p>
                <p class="vpm-section-sub">Every tariff this fish has had. Older versions are kept for reports.</p>
            </div>
            <button type="button" class="vpm-icon-btn" onclick="closeTariffHistory()" aria-label="Close">
                <x-icon name="bi-x-lg" size="base" />
            </button>
        </div>
        <div class="vpm-modal-body" style="max-height:60vh; overflow-y:auto">
            <table class="vpm-table">
                <thead>
                    <tr>
                        <th>Effective</th>
                        <th class="vpm-th-right">Tariff</th>
                        <th class="vpm-th-right">Change</th>
                        <th>Set by</th>
                    </tr>
                </thead>
                <tbody id="tariffHistoryRows"></tbody>
            </table>
        </div>
        @if($editable)
            <form method="POST" id="tariffRemoveForm" class="vpm-modal-footer hidden" style="justify-content:space-between">
                @csrf @method('DELETE')
                <p class="vpm-meta">Removing switches the current tariff off. Its history stays.</p>
                <button type="submit" class="vpm-btn vpm-btn-danger-soft vpm-btn-sm"
                        onclick="return confirm('Remove this tariff? Vendors will see no tariff for this fish until you set a new one.')">
                    <x-icon name="bi-trash" size="xs" /> Remove tariff
                </button>
            </form>
        @endif
    </div>
</div>

@php
    $tariffHistoryPayload = $groups->flatten(1)->mapWithKeys(fn($f) => [$f->id => [
        'name' => $f->name,
        'rows' => $f->tariffHistory->map(fn($g) => [
            'date' => $g->effective_date->format('M j, Y'),
            'price' => (float) $g->tariff_price,
            'by' => $g->setBy?->name,
            'current' => (bool) $g->is_active,
        ])->values(),
        'remove_url' => $editable && $f->currentTariff ? route('supervisor.price-guides.destroy', $f->currentTariff) : null,
    ]]);
@endphp
@push('scripts')
<script>
    // ── Tariff history modal ──────────────────────────────────────
    const TARIFF_HISTORY = @json($tariffHistoryPayload);

    function openTariffHistory(id) {
        const entry = TARIFF_HISTORY[id];
        if (!entry) return;
        document.getElementById('tariffHistoryTitle').textContent = 'Tariff history · ' + entry.name;
        const peso = v => '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const tbody = document.getElementById('tariffHistoryRows');
        tbody.innerHTML = '';
        entry.rows.forEach((row, i) => {
            const older = entry.rows[i + 1];
            const diff = older ? row.price - older.price : null;
            const tr = document.createElement('tr');
            const cells = [
                row.date + (row.current ? ' · current' : ''),
                peso(row.price),
                diff === null ? 'First set' : (diff >= 0 ? '+' : '−') + peso(Math.abs(diff)),
                row.by || '—',
            ];
            cells.forEach((text, c) => {
                const td = document.createElement('td');
                td.textContent = text;
                if (c === 1 || c === 2) td.className = 'vpm-td-right vpm-num';
                if (c === 0 && row.current) td.className = 'vpm-cell-strong';
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        const removeForm = document.getElementById('tariffRemoveForm');
        if (removeForm) {
            removeForm.classList.toggle('hidden', !entry.remove_url);
            removeForm.classList.toggle('flex', !!entry.remove_url);
            if (entry.remove_url) removeForm.action = entry.remove_url;
        }
        document.getElementById('tariffHistoryModal').classList.remove('hidden');
    }

    function closeTariffHistory() {
        document.getElementById('tariffHistoryModal').classList.add('hidden');
    }

    // ── Search: filters rows by local or English name ─────────────
    (function () {
        const search = document.getElementById('tariffSearch');
        if (!search) return;
        search.addEventListener('input', function () {
            const q = search.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('#tariffTable .tariff-group').forEach(group => {
                let groupShown = 0;
                group.querySelectorAll('.tariff-row').forEach(row => {
                    const match = !q || row.dataset.search.includes(q);
                    row.classList.toggle('hidden', !match);
                    if (match) groupShown++;
                });
                group.classList.toggle('hidden', groupShown === 0);
                shown += groupShown;
            });
            document.getElementById('tariffNoMatch').classList.toggle('hidden', shown > 0);
        });
    })();
</script>
@endpush
