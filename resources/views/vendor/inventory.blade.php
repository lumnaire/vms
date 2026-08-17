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
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl"
     style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 13.5px;">
    <i class="bi bi-check-circle-fill flex-shrink-0" style="color: #10b981; font-size: 15px;"></i>
    <span class="font-medium">{{ session('success') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #34d399;">
        <i class="bi bi-x-lg" style="font-size: 13px;"></i>
    </button>
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl"
     style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px;">
    <div class="flex items-center gap-2 font-semibold mb-1.5" style="font-size: 13.5px;">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0" style="color: #ef4444;"></i>
        Please correct the following:
    </div>
    <ul class="space-y-0.5 pl-6" style="list-style: disc; font-size: 12.5px; color: #b91c1c;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Today's Stats ────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

    {{-- Total Stock --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold"
           style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em;">Today's Stock</p>
        <p class="text-slate-800 font-bold mt-1" style="font-size: 24px; line-height: 1;">
            {{ number_format($totalStockToday, 1) }}
        </p>
        <p class="text-slate-400 mt-0.5" style="font-size: 10.5px;">kg submitted</p>
    </div>

    {{-- Pending --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold"
           style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em;">Pending</p>
        <p class="font-bold mt-1" style="font-size: 24px; line-height: 1; color: #d97706;">
            {{ $pendingCount }}
        </p>
        <p class="text-slate-400 mt-0.5" style="font-size: 10.5px;">awaiting review</p>
    </div>

    {{-- Confirmed --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold"
           style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em;">Confirmed</p>
        <p class="font-bold mt-1" style="font-size: 24px; line-height: 1; color: #059669;">
            {{ $confirmedCount }}
        </p>
        <p class="text-slate-400 mt-0.5" style="font-size: 10.5px;">published to board</p>
    </div>

    {{-- Rejected --}}
    <div class="stat-card bg-white rounded-xl p-4 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold"
           style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em;">Rejected</p>
        <p class="font-bold mt-1" style="font-size: 24px; line-height: 1; color: #dc2626;">
            {{ $rejectedCount }}
        </p>
        <p class="text-slate-400 mt-0.5" style="font-size: 10.5px;">not published</p>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Submit New Entry Form (left column) ─────────────────── --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
             style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

            <div class="px-5 py-4 border-b border-slate-100"
                 style="background: linear-gradient(135deg, #0f2d5e, #0a1f3c);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background: rgba(96,165,250,0.2);">
                        <i class="bi bi-plus-circle-fill text-blue-300" style="font-size: 15px;"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-bold" style="font-size: 13.5px;">Log New Entry</h2>
                        <p class="text-blue-300" style="font-size: 11px; margin-top: 1px;">
                            {{ now()->format('l, F j, Y') }}
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('vendor.inventory.store') }}" class="px-5 py-5">
                @csrf

                <div class="space-y-4">

                    {{-- Fish Type --}}
                    <div>
                        <label class="form-label">
                            Fish Type <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="fish_type_id" required class="form-input">
                            <option value="">— Select fish type —</option>
                            @foreach($fishTypes as $fish)
                                <option value="{{ $fish->id }}"
                                    {{ old('fish_type_id') == $fish->id ? 'selected' : '' }}>
                                    {{ $fish->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Quality Class --}}
                    <div>
                        <label class="form-label">
                            Quality Class <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="quality_class" required class="form-input">
                            <option value="">— Select class —</option>
                            @foreach(['First Class', 'Second Class', 'Third Class', 'Fourth Class', 'Special Class'] as $class)
                                <option value="{{ $class }}"
                                    {{ old('quality_class') == $class ? 'selected' : '' }}>
                                    {{ $class }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-slate-400" style="font-size: 11px;">
                            One entry per fish type + quality class per day.
                        </p>
                    </div>

                    {{-- Price per kg --}}
                    <div>
                        <label class="form-label">
                            Price per kg (₱) <span style="color:#ef4444;">*</span>
                        </label>
                        <div style="position:relative;">
                            <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px; font-weight:600;">₱</span>
                            <input type="number" name="price_per_kg"
                                   value="{{ old('price_per_kg') }}"
                                   step="0.01" min="0.01" max="99999.99"
                                   placeholder="0.00"
                                   required class="form-input" style="padding-left: 28px;">
                        </div>
                    </div>

                    {{-- Stock kg --}}
                    <div>
                        <label class="form-label">
                            Total Stock (kg) <span style="color:#ef4444;">*</span>
                        </label>
                        <div style="position:relative;">
                            <input type="number" name="stock_kg" id="stockKgInput"
                                   value="{{ old('stock_kg') }}"
                                   step="0.1" min="0.1"
                                   placeholder="0.0"
                                   required class="form-input"
                                   oninput="syncReleased()">
                            <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px;">kg</span>
                        </div>
                        <p class="mt-1 text-slate-400" style="font-size: 11px;">Total fish you brought to the market today.</p>
                    </div>

                    {{-- Released kg --}}
                    <div>
                        <label class="form-label">
                            Released for Sale (kg) <span style="color:#ef4444;">*</span>
                        </label>
                        <div style="position:relative;">
                            <input type="number" name="released_kg" id="releasedKgInput"
                                   value="{{ old('released_kg') }}"
                                   step="0.1" min="0.1"
                                   placeholder="0.0"
                                   required class="form-input">
                            <span style="position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px;">kg</span>
                        </div>
                        <p class="mt-1 text-slate-400" style="font-size: 11px;">Cannot exceed total stock above.</p>
                    </div>

                </div>

                <button type="submit"
                        class="mt-6 w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-white font-semibold transition-colors"
                        style="background: #059669; font-size: 13.5px;"
                        onmouseover="this.style.background='#047857'"
                        onmouseout="this.style.background='#059669'">
                    <i class="bi bi-send-fill" style="font-size: 13px;"></i>
                    Submit Entry
                </button>

                {{-- Note about locking --}}
                <div class="mt-3 rounded-lg px-3 py-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <p style="font-size: 11px; color: #64748b; line-height: 1.5;">
                        <i class="bi bi-lock-fill mr-1" style="color: #94a3b8;"></i>
                        Entries are <strong>locked after submission</strong> and cannot be deleted.
                        Rejected entries may be resubmitted if the fish type + quality class is different.
                    </p>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Today's Entries Table (right column) ────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
             style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Today's Entries</h2>
                    <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                        {{ now()->format('F j, Y') }} · {{ $todayEntries->count() }} {{ Str::plural('entry', $todayEntries->count()) }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                      style="font-size: 11px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                    <i class="bi bi-calendar-day" style="font-size: 10px;"></i>
                    Today
                </span>
            </div>

            @if($todayEntries->isEmpty())
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3"
                     style="background: #f8fafc;">
                    <i class="bi bi-inbox" style="font-size: 22px; color: #cbd5e1;"></i>
                </div>
                <p class="text-slate-500 font-semibold" style="font-size: 13px;">No entries yet today</p>
                <p class="text-slate-400 mt-1" style="font-size: 12px;">
                    Use the form on the left to log your first entry.
                </p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 560px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Fish Type</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Class</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Price/kg</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Stock</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Released</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Status</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Action</th>
                        </tr>
                     </thead>
                     <tbody>
                         @foreach($todayEntries as $entry)
                         <tr style="border-bottom: 1px solid #f8fafc; transition: background 0.1s;"
                             onmouseover="this.style.background='#f8faff'"
                             onmouseout="this.style.background='transparent'">

                             <td class="px-4 py-3">
                                 <div class="flex items-center gap-2">
                                     <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                          style="background: #eff6ff;">
                                         <i class="bi bi-water text-blue-500" style="font-size: 12px;"></i>
                                     </div>
                                     <span class="text-slate-700 font-semibold" style="font-size: 13px;">
                                         {{ $entry->fishType->name }}
                                     </span>
                                 </div>
                             </td>

                             <td class="px-4 py-3">
                                 <span style="font-size: 12px; color: #64748b; font-weight: 500;">
                                     {{ $entry->quality_class }}
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="font-semibold text-slate-700" style="font-size: 13px;">
                                     ₱{{ number_format($entry->price_per_kg, 2) }}
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="text-slate-600" style="font-size: 12.5px;">
                                     {{ number_format($entry->stock_kg, 1) }} kg
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-right">
                                 <span class="text-slate-600" style="font-size: 12.5px;">
                                     {{ number_format($entry->released_kg, 1) }} kg
                                 </span>
                             </td>

                             <td class="px-4 py-3 text-center">
                                 @if($entry->status === 'pending')
                                     <span class="status-pending inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                           style="font-size: 10.5px;">
                                         <i class="bi bi-clock" style="font-size: 9px;"></i> Pending
                                     </span>
                                 @elseif($entry->status === 'confirmed')
                                     <span class="status-confirmed inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                           style="font-size: 10.5px;">
                                         <i class="bi bi-check-circle-fill" style="font-size: 9px;"></i> Confirmed
                                     </span>
                                 @else
                                     <span class="status-rejected inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                           style="font-size: 10.5px;">
                                         <i class="bi bi-x-circle-fill" style="font-size: 9px;"></i> Rejected
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
                                             class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold transition-colors"
                                             style="font-size: 10.5px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;"
                                             onmouseover="this.style.background='#fee2e2'"
                                             onmouseout="this.style.background='#fef2f2'">
                                         <i class="bi bi-x-lg" style="font-size: 9px;"></i> Cancel
                                     </button>
                                 </form>
                                 @else
                                 <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                       style="font-size: 10.5px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                                     <i class="bi bi-check-lg" style="font-size: 9px;"></i> No action required
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
        <div class="mt-5 bg-white rounded-xl border border-slate-100 overflow-hidden"
             style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Past 7 Days</h2>
                <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                    Read-only historical entries — locked after submission day
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 560px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Date</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Fish Type</th>
                            <th class="text-left px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Class</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Price/kg</th>
                            <th class="text-right px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Stock</th>
                            <th class="text-center px-4 py-3 text-slate-400 font-semibold"
                                style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentEntries as $entry)
                        <tr style="border-bottom: 1px solid #f8fafc; opacity: 0.85; transition: background 0.1s;"
                            onmouseover="this.style.background='#f8fafc'; this.style.opacity='1'"
                            onmouseout="this.style.background='transparent'; this.style.opacity='0.85'">

                            <td class="px-4 py-3">
                                <span class="text-slate-500" style="font-size: 12px;">
                                    {{ $entry->entry_date->format('M j') }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <span class="text-slate-700 font-medium" style="font-size: 13px;">
                                    {{ $entry->fishType->name }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <span class="text-slate-500" style="font-size: 12px;">{{ $entry->quality_class }}</span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span class="text-slate-700" style="font-size: 12.5px;">
                                    ₱{{ number_format($entry->price_per_kg, 2) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span class="text-slate-500" style="font-size: 12px;">
                                    {{ number_format($entry->stock_kg, 1) }} kg
                                </span>
                            </td>

                            <td class="px-4 py-3 text-center">
                                @if($entry->status === 'confirmed')
                                    <span class="status-confirmed inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                          style="font-size: 10.5px;">
                                        <i class="bi bi-check-circle-fill" style="font-size: 9px;"></i> Confirmed
                                    </span>
                                @elseif($entry->status === 'rejected')
                                    <span class="status-rejected inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                          style="font-size: 10.5px;">
                                        <i class="bi bi-x-circle-fill" style="font-size: 9px;"></i> Rejected
                                    </span>
                                @else
                                    <span class="status-pending inline-flex items-center gap-1 px-2.5 py-1 rounded-full font-semibold"
                                          style="font-size: 10.5px;">
                                        <i class="bi bi-clock" style="font-size: 9px;"></i> Pending
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
</script>
@endpush