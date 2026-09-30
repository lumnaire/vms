@extends('layouts.app')

@section('title', 'My Inventory')
@section('subtitle', 'Daily Fish Entry · Vendor View')

@push('styles')
<style>
    .form-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13.5px;
        color: #334155;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: white;
    }
    .form-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
    }
    .form-input::placeholder { color: #cbd5e1; }
    .form-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
    .form-input.is-over-guide,
    .form-input.is-over-guide:focus {
        border-color: #f87171;
        color: #991b1b;
        -webkit-text-fill-color: #991b1b;
        font-weight: 600;
        box-shadow: 0 0 0 3px rgba(248,113,113,0.15);
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        letter-spacing: 0.01em;
    }
    .status-pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .status-confirmed { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .status-rejected  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>
@endpush

@section('content')

{{-- ── Flash Messages ───────────────────────────────────────────── --}}
@if(session('success'))
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-[13.5px] bg-success-50"
     style="border: 1px solid #a7f3d0; color: #065f46">
    <x-icon name="bi-check-circle-fill" size="base" class="flex-shrink-0 text-success-500" />
    <span class="font-medium">{{ session('success') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #34d399;">
        <x-icon name="bi-x-lg" size="md" />
    </button>
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-[13px] bg-danger-50 border border-danger-200 text-danger-800"
    >
    <div class="flex items-center gap-2 font-semibold mb-1.5 text-[13.5px]">
        <x-icon name="bi-exclamation-circle-fill" class="flex-shrink-0 text-danger-500" />
        Please correct the following:
    </div>
    <ul class="space-y-0.5 pl-6 text-[12.5px] text-danger-700" style="list-style: disc">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Today's Stats ────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

    {{-- Total Stock --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100 shadow-card"
        >
        <p class="text-slate-400 font-semibold text-[10px] uppercase tracking-[0.07em]"
          >Today's Stock</p>
        <p class="text-slate-800 font-bold mt-1 leading-[1]" style="font-size: 24px">
            {{ number_format($totalStockToday, 1) }}
        </p>
        <p class="text-slate-400 mt-0.5 text-[10.5px]">kg submitted</p>
    </div>

    {{-- Pending --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100 shadow-card"
        >
        <p class="text-slate-400 font-semibold text-[10px] uppercase tracking-[0.07em]"
          >Pending</p>
        <p class="font-bold mt-1 leading-[1] text-warning-600" style="font-size: 24px">
            {{ $pendingCount }}
        </p>
        <p class="text-slate-400 mt-0.5 text-[10.5px]">awaiting review</p>
    </div>

    {{-- Confirmed --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100 shadow-card"
        >
        <p class="text-slate-400 font-semibold text-[10px] uppercase tracking-[0.07em]"
          >Confirmed</p>
        <p class="font-bold mt-1 leading-[1] text-success-600" style="font-size: 24px">
            {{ $confirmedCount }}
        </p>
        <p class="text-slate-400 mt-0.5 text-[10.5px]">published to board</p>
    </div>

    {{-- Rejected --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100 shadow-card"
        >
        <p class="text-slate-400 font-semibold text-[10px] uppercase tracking-[0.07em]"
          >Rejected</p>
        <p class="font-bold mt-1 leading-[1] text-danger-600" style="font-size: 24px">
            {{ $rejectedCount }}
        </p>
        <p class="text-slate-400 mt-0.5 text-[10.5px]">not published</p>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Submit New Entry Form (left column) ─────────────────── --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card"
            >

            <div class="px-5 py-4 border-b border-slate-100"
                 style="background: linear-gradient(135deg, #0f2d5e, #0a1f3c);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background: rgba(96,165,250,0.2);">
                        <x-icon name="bi-plus-circle-fill" size="base" class="text-blue-300" />
                    </div>
                    <div>
                        <h2 class="text-white font-bold text-[13.5px]">Log New Entry</h2>
                        <p class="text-blue-300 text-[11px] mt-px">
                            {{ now()->format('l, F j, Y') }}
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('vendor.inventory.store') }}" class="px-5 py-5">
                @csrf

                <div class="space-y-4">

                    {{-- Quality Class --}}
                    <div>
                        <label class="form-label">
                            Quality Class <span class="text-danger-500">*</span>
                        </label>
                        <select name="quality_class" id="qualityClassSelect" required class="form-input">
                            <option value="">— Select class —</option>
                            @foreach(\App\Models\FishType::QUALITY_CLASSES as $class)
                                <option value="{{ $class }}"
                                    {{ old('quality_class') == $class ? 'selected' : '' }}>
                                    {{ $class }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-slate-400 text-[11px]">
                            One entry per fish type + quality class per day.
                        </p>
                    </div>

                    {{-- Fish Type (filtered by the selected quality class) --}}
                    <div>
                        <label class="form-label">
                            Fish Type <span class="text-danger-500">*</span>
                        </label>
                        <select name="fish_type_id" id="fishTypeSelect" required class="form-input">
                            <option value="">— Select fish type —</option>
                            @foreach($fishTypes as $fish)
                                <option value="{{ $fish->id }}"
                                    data-quality-class="{{ $fish->quality_class }}"
                                    {{ old('fish_type_id') == $fish->id ? 'selected' : '' }}>
                                    {{ $fish->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price per kg --}}
                    <div>
                        <label class="form-label" for="pricePerKgInput">
                            Price per kg (₱) <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute text-[13px] font-semibold left-3 top-1/2 -translate-y-1/2 text-slate-400">₱</span>
                            <input type="number" name="price_per_kg" id="pricePerKgInput"
                                   value="{{ old('price_per_kg') }}"
                                   step="0.01" min="0.01" max="99999.99"
                                   placeholder="0.00"
                                   oninput="checkPriceGuide()"
                                   required class="form-input" style="padding-left: 28px;">
                        </div>
                        <p class="mt-1 text-slate-400 text-[11px]" id="priceGuideHint"
                           >Select a fish type to see its price guideline.</p>

                        {{-- Shown when the entered price is above the guideline --}}
                        <div id="priceGuideWarning" class="hidden mt-2">
                            <div class="flex items-start gap-1.5 rounded-lg px-2.5 py-2 text-[11px] leading-[1.45]"
                                 style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                                <span class="flex-shrink-0 mt-px" id="priceGuideWarningIcon"
                                      title="Price exceeds the price guideline.">
                                    <x-icon name="bi-exclamation-triangle-fill" size="xs" />
                                </span>
                                <span>
                                    <strong class="font-semibold">Price exceeds the price guideline.</strong>
                                    <span id="priceGuideWarningText"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Stock kg --}}
                    <div>
                        <label class="form-label">
                            Total Stock (kg) <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="stock_kg" id="stockKgInput"
                                   value="{{ old('stock_kg') }}"
                                   step="0.1" min="0.1"
                                   placeholder="0.0"
                                   required class="form-input"
                                   oninput="syncReleased()">
                            <span class="absolute text-[12px] top-1/2 -translate-y-1/2 text-slate-400" style="right:12px">kg</span>
                        </div>
                        <p class="mt-1 text-slate-400 text-[11px]">Total fish you brought to the market today.</p>
                    </div>

                    {{-- Released kg --}}
                    <div>
                        <label class="form-label">
                            Released for Sale (kg) <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="released_kg" id="releasedKgInput"
                                   value="{{ old('released_kg') }}"
                                   step="0.1" min="0.1"
                                   placeholder="0.0"
                                   required class="form-input">
                            <span class="absolute text-[12px] top-1/2 -translate-y-1/2 text-slate-400" style="right:12px">kg</span>
                        </div>
                        <p class="mt-1 text-slate-400 text-[11px]">Cannot exceed total stock above.</p>
                    </div>

                </div>

                <button type="submit"
                        class="mt-6 w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-white font-semibold transition-colors text-[13.5px] bg-success-600"
                       
                        onmouseover="this.style.background='#047857'"
                        onmouseout="this.style.background='#059669'">
                    <x-icon name="bi-send-fill" size="md" />
                    Submit Entry
                </button>

                {{-- Note about locking --}}
                <div class="mt-3 rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                    <p class="text-[11px] leading-[1.5] text-slate-500">
                        <x-icon name="bi-lock-fill" class="mr-1 text-slate-400" />
                        Entries are <strong>locked after submission</strong> and cannot be deleted.
                        Rejected entries may be resubmitted if the fish type + quality class is different.
                    </p>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Today's Entries Table (right column) ────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card"
            >

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-slate-700 font-bold text-[13.5px]">Today's Entries</h2>
                    <p class="text-slate-400 text-[11px] mt-px">
                        {{ now()->format('F j, Y') }} · {{ $todayEntries->count() }} {{ Str::plural('entry', $todayEntries->count()) }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-[11px] bg-brand-50 text-brand-600 border border-brand-200"
                     >
                    <x-icon name="bi-calendar-day" size="2xs" />
                    Today
                </span>
            </div>

            @if($todayEntries->isEmpty())
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3 bg-surface-subtle"
                    >
                    <x-icon name="bi-inbox" size="2xl" class="text-slate-300" />
                </div>
                <p class="text-slate-500 font-semibold text-[13px]">No entries yet today</p>
                <p class="text-slate-400 mt-1 text-[12px]">
                    Use the form on the left to log your first entry.
                </p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 560px;">
                    <thead>
                        <tr class="bg-surface-subtle" style="border-bottom: 1px solid #f1f5f9">
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Fish Type</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Class</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Price/kg</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Stock</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Released</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Status</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Action</th>
                        </tr>
                     </thead>
                     <tbody>
                         @foreach($todayEntries as $entry)
                         <tr style="border-bottom: 1px solid #f8fafc; transition: background 0.1s;"
                             onmouseover="this.style.background='#f8faff'"
                             onmouseout="this.style.background='transparent'">

                             <td class="px-4 py-3">
                                 <div class="flex items-center gap-2">
                                     <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 bg-brand-50"
                                         >
                                         <x-icon name="bi-water" size="sm" class="text-blue-500" />
                                     </div>
                                     <span class="text-slate-700 font-semibold text-[13px]">
                                         {{ $entry->fishType->name }}
                                     </span>
                                 </div>
                             </td>

                             <td class="px-4 py-3">
                                 <span class="text-[12px] text-slate-500" style="font-weight: 500">
                                     {{ $entry->quality_class }}
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="font-semibold text-slate-700 text-[13px]">
                                     ₱{{ number_format($entry->price_per_kg, 2) }}
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="text-slate-600 text-[12.5px]">
                                     {{ number_format($entry->stock_kg, 1) }} kg
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="text-slate-600 text-[12.5px]">
                                     {{ number_format($entry->released_kg, 1) }} kg
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-center">
                                 @if($entry->status === 'pending')
                                     <span class="status-pending inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                          >
                                         <x-icon name="bi-clock" size="2xs" /> Pending
                                     </span>
                                 @elseif($entry->status === 'confirmed')
                                     <span class="status-confirmed inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                          >
                                         <x-icon name="bi-check-circle-fill" size="2xs" /> Confirmed
                                     </span>
                                 @else
                                     <span class="status-rejected inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                          >
                                         <x-icon name="bi-x-circle-fill" size="2xs" /> Rejected
                                     </span>
                                 @endif
                             </td>

                             <td class="px-4 py-3 text-center">
                                 @if($entry->status === 'pending')
                                 <form method="POST" action="{{ route('vendor.inventory.destroy', $entry) }}"
                                       onsubmit="return confirm('Are you sure you want to cancel this entry?')">
                                     @csrf
                                     @method('DELETE')
                                     <button type="submit"
                                             class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold transition-colors text-[10.5px] bg-danger-50 text-danger-800 border border-danger-200"
                                            
                                             onmouseover="this.style.background='#fee2e2'"
                                             onmouseout="this.style.background='#fef2f2'">
                                         <x-icon name="bi-x-lg" size="2xs" /> Cancel
                                     </button>
                                 </form>
                                 @else
                                 <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px] bg-success-50"
                                       style="color: #166534; border: 1px solid #bbf7d0">
                                     <x-icon name="bi-check-lg" size="2xs" /> No action required
                                 </span>
                                 @endif
                             </td>

                         </tr>
                         @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>

        {{-- ── Recent Entries (past 7 days) ─────────────────────── --}}
        @if($recentEntries->isNotEmpty())
        <div class="mt-5 bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card"
            >

            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-slate-700 font-bold text-[13.5px]">Past 7 Days</h2>
                <p class="text-slate-400 text-[11px] mt-px">
                    Read-only historical entries — locked after submission day
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 560px;">
                    <thead>
                        <tr class="bg-surface-subtle" style="border-bottom: 1px solid #f1f5f9">
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Date</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Fish Type</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Class</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Price/kg</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Stock</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Remaining</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                               >Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentEntries as $entry)
                        <tr style="border-bottom: 1px solid #f8fafc; {{ $entry->isStale() ? 'background:#fef2f2;' : 'opacity: 0.85;' }} transition: background 0.1s;"
                            onmouseover="this.style.background='#fef2f2'; this.style.opacity='1'"
                            onmouseout="this.style.background='{{ $entry->isStale() ? '#fef2f2' : 'transparent' }}'; this.style.opacity='{{ $entry->isStale() ? '1' : '0.85' }}'">

                            <td class="px-4 py-3">
                                <span class="text-slate-500 text-[12px]">
                                    {{ $entry->entry_date->format('M j') }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <span class="text-slate-700 font-medium text-[13px]">
                                    {{ $entry->fishType->name }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <span class="text-slate-500 text-[12px]">{{ $entry->quality_class }}</span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span class="text-slate-700 text-[12.5px]">
                                    ₱{{ number_format($entry->price_per_kg, 2) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span class="text-slate-500 text-[12px]">
                                    {{ number_format($entry->stock_kg, 1) }} kg
                                </span>
                            </td>

                            {{-- Remaining = released − declared sold. Unsold stock that has
                                 aged past the freshness window is flagged in red with its age,
                                 so it is impossible to miss on a list of old entries. --}}
                            <td class="px-4 py-3 text-right">
                                @php
                                    $isStale = $entry->isStale();
                                    // Built outside the markup: a `>` inside {{ }} within an
                                    // HTML attribute makes Blade terminate the echo early and
                                    // emit broken PHP.
                                    $staleTitle = $isStale
                                        ? '₱' . number_format((float) $entry->getRemainingStockValue(), 2)
                                            . ' of unsold stock held for ' . $entry->getAgeInDays() . ' days'
                                        : '';
                                @endphp
                                @if($isStale)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-danger-50 text-danger-700"
                                          style="border:1px solid #fecaca" title="{{ $staleTitle }}">
                                        <x-icon name="bi-exclamation-octagon-fill" size="2xs" />
                                        {{ number_format($entry->getRemainingStock(), 1) }} kg
                                        <span class="font-normal">· {{ $entry->getAgeInDays() }}d</span>
                                    </span>
                                @else
                                    <span class="text-slate-600 text-[12px] font-semibold">
                                        {{ number_format($entry->getRemainingStock(), 1) }} kg
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($entry->status === 'confirmed')
                                    <span class="status-confirmed inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                         >
                                        <x-icon name="bi-check-circle-fill" size="2xs" /> Confirmed
                                    </span>
                                @elseif($entry->status === 'rejected')
                                    <span class="status-rejected inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                         >
                                        <x-icon name="bi-x-circle-fill" size="2xs" /> Rejected
                                    </span>
                                @else
                                    <span class="status-pending inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold text-[10.5px]"
                                         >
                                        <x-icon name="bi-clock" size="2xs" /> Pending
                                    </span>
                                @endif
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
        @endif

    </div>
</div>

@endsection

@push('scripts')
<script>
    // Auto-fill released_kg when stock_kg is entered
    function syncReleased() {
        const stock    = document.getElementById('stockKgInput').value;
        const released = document.getElementById('releasedKgInput');
        if (!released.value || parseFloat(released.value) > parseFloat(stock)) {
            released.value = stock;
        }
    }

    // Filter the fish type dropdown based on the chosen quality class
    const qcSelect    = document.getElementById('qualityClassSelect');
    const fishSelect  = document.getElementById('fishTypeSelect');
    const allFishOpts = Array.from(fishSelect.options).filter(o => o.value !== '');

    // Active price guidelines, keyed by "<fish_type_id>_<quality class>"
    const PRICE_GUIDES  = {!! json_encode($priceGuides) !!};
    const priceInput    = document.getElementById('pricePerKgInput');
    const guideHint     = document.getElementById('priceGuideHint');
    const guideWarning  = document.getElementById('priceGuideWarning');
    const guideWarnText = document.getElementById('priceGuideWarningText');
    const guideWarnIcon = document.getElementById('priceGuideWarningIcon');
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function filterFishTypes() {
        const selectedClass = qcSelect.value;
        const previousValue = fishSelect.value;

        fishSelect.innerHTML = '<option value="">— Select fish type —</option>';

        allFishOpts.forEach(opt => {
            if (!selectedClass || opt.dataset.qualityClass === selectedClass) {
                fishSelect.add(opt.cloneNode(true));
            }
        });

        const hasPrevious = Array.from(fishSelect.options).some(o => o.value === previousValue);
        fishSelect.value = hasPrevious ? previousValue : '';

        checkPriceGuide();
    }

    // Flags the price red + shows the warning when it is above the guideline
    function checkPriceGuide() {
        const guide = fishSelect.value
            ? PRICE_GUIDES[fishSelect.value + '_' + qcSelect.value]
            : undefined;
        const price = parseFloat(priceInput.value);

        if (!guide) {
            priceInput.classList.remove('is-over-guide');
            guideWarning.classList.add('hidden');
            guideHint.textContent = fishSelect.value
                ? 'No price guideline is set for this fish type and quality class.'
                : 'Select a fish type to see its price guideline.';
            return;
        }

        guideHint.textContent = 'Guideline: Cheap ≤ ' + peso(guide.cheap)
            + ' · Moderate ≤ ' + peso(guide.moderate) + ' per kg.';

        const isOver = Number.isFinite(price) && price > guide.moderate;

        priceInput.classList.toggle('is-over-guide', isOver);
        guideWarning.classList.toggle('hidden', !isOver);

        if (isOver) {
            guideWarnText.textContent = ' The guideline for this fish and quality class is up to '
                + peso(guide.moderate) + ' per kg. You may still submit, but staff '
                + 'will see this as an expensive price.';
            guideWarnIcon.setAttribute('title', 'Price exceeds the price guideline (max '
                + peso(guide.moderate) + ' per kg).');
        }
    }

    qcSelect.addEventListener('change', filterFishTypes);
    fishSelect.addEventListener('change', checkPriceGuide);
    filterFishTypes(); // run once so old() input is preserved after validation errors
    checkPriceGuide();
</script>
@endpush