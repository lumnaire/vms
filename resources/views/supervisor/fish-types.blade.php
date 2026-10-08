@extends('layouts.app')

@section('title', 'Fish Type Management')
@section('subtitle', 'Add and edit the fish types available in the market')

@push('styles')
<style>
    @layer components {
    .ft-table-row { transition: background 0.12s ease; }
    .ft-table-row:hover { background: #f8fafc; }

    .btn-ft-primary {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600;
        background: #1d4ed8; color: #fff; border: none; cursor: pointer;
        transition: background 0.15s;
    }
    .btn-ft-primary:hover { background: #1e40af; }

    .btn-ft-outline {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 7px; font-size: 12px; font-weight: 600;
        background: #fff; border: 1px solid #e2e8f0; color: #475569; cursor: pointer;
        transition: all 0.12s;
    }
    .btn-ft-outline:hover { border-color: #94a3b8; color: #1e293b; background: #f8fafc; }

    /* Modal */
    .ft-modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.45);
        z-index: 1000; align-items: center; justify-content: center; padding: 16px;
    }
    .ft-modal-box {
        background: #fff; border-radius: 16px; width: 100%; max-width: 440px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.18); overflow: hidden;
    }
    .ft-modal-header {
        padding: 18px 20px 14px; border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between;
    }
    .ft-modal-body   { padding: 20px; }
    .ft-modal-footer {
        padding: 14px 20px; border-top: 1px solid #f1f5f9;
        display: flex; justify-content: flex-end; gap: 10px; background: #fafafa;
    }
    .ft-form-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px; display: block; }
    .ft-form-input {
        width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 9px;
        font-size: 13px; color: #334155; outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        box-sizing: border-box;
    }
    .ft-form-input:focus { border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(147,197,253,0.25); }
    .ft-form-input.danger:focus { border-color: #fca5a5; box-shadow: 0 0 0 3px rgba(252,165,165,0.25); }
    .ft-btn-cancel {
        padding: 8px 16px; border-radius: 9px; font-size: 13px; font-weight: 600;
        background: #f1f5f9; color: #475569; border: none; cursor: pointer;
        transition: background 0.12s;
    }
    .ft-btn-cancel:hover { background: #e2e8f0; }
    .ft-btn-save {
        padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
        background: #1d4ed8; color: #fff; border: none; cursor: pointer;
        transition: background 0.15s;
    }
    .ft-btn-save:hover { background: #1e40af; }
    }
</style>
@endpush

@section('content')

{{-- ── Page Header ──────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-slate-800 font-bold" style="font-size: 20px;">Fish Type Management</h1>
        <p class="text-slate-400 mt-0.5 text-[12.5px]">
            Add and edit the fish types available in the market. A fish type that is
            listed is in use &mdash; it is corrected, never switched off.
        </p>
    </div>
    {{-- <button class="btn-ft-primary" onclick="ftOpenModal('addModal')">
        <i class="bi bi-plus-lg"></i> Add Fish Type
    </button> --}}
</div>

{{-- ── Flash Messages ───────────────────────────────────────── --}}
@if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-4 bg-success-50"
         style="border:1px solid #bbf7d0">
        <x-icon name="bi-check-circle-fill" size="base" class="text-emerald-500" />
        <p class="text-emerald-700 font-semibold text-[13px]">{{ session('success') }}</p>
    </div>
@endif
@if(session('error'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-4 bg-danger-50 border border-danger-200"
        >
        <x-icon name="bi-exclamation-circle-fill" size="base" class="text-rose-500" />
        <p class="text-rose-700 font-semibold text-[13px]">{{ session('error') }}</p>
    </div>
@endif

{{-- ── Stat Cards ───────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-4 mb-6" style="max-width: 420px;">
    <div class="bg-white rounded-xl p-4 border border-slate-100 shadow-card">
        <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Fish Types</p>
        <p class="text-blue-600 font-bold mt-1 leading-[1]" style="font-size:30px">{{ $fishTypes->count() }}</p>
        <p class="text-slate-400 mt-1 text-[11px]">In the market catalogue</p>
    </div>
    <div class="bg-white rounded-xl p-4 border border-slate-100 shadow-card">
        <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Classified</p>
        <p class="text-emerald-600 font-bold mt-1 leading-[1]" style="font-size:30px">{{ $fishTypes->whereNotNull('quality_class')->count() }}</p>
        <p class="text-slate-400 mt-1 text-[11px]">With a quality class</p>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
        <p class="text-slate-700 font-bold text-[13.5px]">All Fish Types</p>
        <span class="text-slate-400 text-[11.5px]">
            {{ $fishTypes->count() }} total &middot; cheapest class first
        </span>
    </div>

    <div class="overflow-x-auto">
    <table style="width:100%; border-collapse:collapse; min-width: 520px;">
        <thead>
            <tr class="bg-surface-subtle" style="border-bottom:1px solid #f1f5f9">
                <th class="text-left text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]" style="width:50px">#</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Fish Type Name</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]">Quality Class</th>
                <th class="text-right text-slate-400 font-semibold px-4 py-3 text-[11px] uppercase tracking-[0.07em]" style="width:90px">Guides</th>
                <th class="text-right text-slate-400 font-semibold px-5 py-3 text-[11px] uppercase tracking-[0.07em]" style="width:110px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fishTypes as $index => $ft)
            <tr class="ft-table-row" style="border-bottom:1px solid #f1f5f9;">
                <td class="px-5 py-3 text-slate-400 font-medium text-[13px]">{{ $index + 1 }}</td>
                <td class="px-4 py-3">
                    <span class="font-semibold text-[13.5px] text-slate-700">
                        {{ $ft->name }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if($ft->quality_class)
                        <x-quality-badge :quality="$ft->quality_class" />
                    @else
                        <span class="text-slate-300 text-[11px]">&mdash;</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <span class="text-slate-600 text-[12.5px] font-semibold">{{ $ft->price_guides_count }}</span>
                </td>
                <td class="px-5 py-3 text-right">
                    {{-- Edit is the only action. A fish type has no on/off switch
                         and cannot be deleted, so this column never has to
                         change width as rows move between states. --}}
                    <button class="btn-ft-outline"
                            onclick="ftOpenModal('editModal{{ $ft->id }}')">
                        <x-icon name="bi-pencil" size="xs" /> Edit
                    </button>
                </td>
            </tr>

            {{-- ── Edit Modal (per row) ──────────────────────────── --}}
            <div id="editModal{{ $ft->id }}" class="ft-modal-overlay hidden flex"
                 onclick="if(event.target===this) ftCloseModal('editModal{{ $ft->id }}')">
                <div class="ft-modal-box">
                    <form action="{{ route('supervisor.fish-types.update', $ft) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="ft-modal-header">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                     style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8);">
                                    <x-icon name="bi-pencil" size="sm" class="text-white" />
                                </div>
                                <div>
                                    <p class="text-slate-800 font-bold text-[14px]">Edit Fish Type</p>
                                    <p class="text-slate-400 text-[11px]">Update the name or class of this fish type</p>
                                </div>
                            </div>
                            <button class="text-slate-400" type="button" onclick="ftCloseModal('editModal{{ $ft->id }}')"
                                    style="border:none; background:transparent; cursor:pointer; padding:4px">
                                <x-icon name="bi-x-lg" size="md" />
                            </button>
                        </div>
                        <div class="ft-modal-body">
                            @php $reopenedEdit = session('open_edit_modal') == $ft->id; @endphp
                            @if($reopenedEdit && $errors->any())
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg mb-3 bg-danger-50 border border-danger-200">
                                    <x-icon name="bi-exclamation-circle-fill" size="md" class="text-rose-400" />
                                    <p class="text-rose-600 font-semibold text-[12px]">{{ $errors->first() }}</p>
                                </div>
                            @endif
                            <label class="ft-form-label">Fish Type Name</label>
                            <input type="text" name="name"
                                   class="ft-form-input {{ ($reopenedEdit && $errors->has('name')) ? 'danger' : '' }}"
                                   value="{{ $reopenedEdit ? old('name', $ft->name) : $ft->name }}"
                                   required maxlength="100">

                            <label class="ft-form-label" style="margin-top:14px;">Quality Class <span class="text-danger-500">*</span></label>
                            <select name="quality_class"
                                    class="ft-form-input {{ ($reopenedEdit && $errors->has('quality_class')) ? 'danger' : '' }}"
                                    required>
                                @foreach(\App\Models\FishType::QUALITY_CLASSES as $class)
                                    <option value="{{ $class }}"
                                        {{ ($reopenedEdit ? old('quality_class', $ft->quality_class) : $ft->quality_class) == $class ? 'selected' : '' }}>
                                        {{ $class }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-[11px] text-slate-400">
                                Changing the class moves this fish type's price brackets and
                                inventory to the new class so they stay in step.
                            </p>
                        </div>
                        <div class="ft-modal-footer">
                            <button type="button" class="ft-btn-cancel"
                                    onclick="ftCloseModal('editModal{{ $ft->id }}')">Cancel</button>
                            <button type="submit" class="ft-btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            @empty
            <tr>
                <td colspan="5" class="text-center py-16">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mb-1 bg-brand-50"
                            >
                            <x-icon name="bi-fish" size="2xl" class="text-blue-400" />
                        </div>
                        <p class="text-slate-500 font-semibold text-[13px]">No fish types found</p>
                        <p class="text-slate-400 text-[12px]">
                            Load the market catalogue with
                            <span class="font-mono text-slate-500">php artisan db:seed --class=FishTypeSeeder</span>.
                        </p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- ── Add Fish Type Modal ──────────────────────────────────── --}}
<div id="addModal" class="ft-modal-overlay hidden flex"
     onclick="if(event.target===this) ftCloseModal('addModal')">
    <div class="ft-modal-box">
        <form action="{{ route('supervisor.fish-types.store') }}" method="POST">
            @csrf
            <div class="ft-modal-header">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                         style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8);">
                        <x-icon name="bi-plus-lg" size="md" class="text-white" />
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold text-[14px]">Add New Fish Type</p>
                        <p class="text-slate-400 text-[11px]">Enter the name and quality class</p>
                    </div>
                </div>
                <button class="text-slate-400" type="button" onclick="ftCloseModal('addModal')"
                        style="border:none; background:transparent; cursor:pointer; padding:4px">
                    <x-icon name="bi-x-lg" size="md" />
                </button>
            </div>
            <div class="ft-modal-body">
                @error('name')
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg mb-3 bg-danger-50 border border-danger-200"
                        >
                        <x-icon name="bi-exclamation-circle-fill" size="md" class="text-rose-400" />
                        <p class="text-rose-600 font-semibold text-[12px]">{{ $message }}</p>
                    </div>
                @enderror
                <label class="ft-form-label">Fish Type Name</label>
                <input type="text" name="name"
                       class="ft-form-input @error('name') border-rose-300 @enderror"
                       value="{{ old('name') }}"
                       placeholder="e.g. Bangus, Tilapia, Alumahan"
                       required maxlength="100">
                <p class="text-slate-400 mt-2 text-[11.5px]">
                    <i class="bi bi-info-circle"></i> Name will be auto-formatted to Title Case.
                </p>

                <label class="ft-form-label" style="margin-top:14px;">Quality Class <span class="text-danger-500">*</span></label>
                <select name="quality_class" class="ft-form-input @error('quality_class') border-rose-300 @enderror" required>
                    <option value="">&mdash; Select class &mdash;</option>
                    @foreach(\App\Models\FishType::QUALITY_CLASSES as $class)
                        <option value="{{ $class }}" {{ old('quality_class') == $class ? 'selected' : '' }}>
                            {{ $class }}
                        </option>
                    @endforeach
                </select>
                @error('quality_class')
                    <p class="text-[11px] text-danger-600" style="margin-top:4px">{{ $message }}</p>
                @enderror
            </div>
            <div class="ft-modal-footer">
                <button type="button" class="ft-btn-cancel" onclick="ftCloseModal('addModal')">Cancel</button>
                <button type="submit" class="ft-btn-save">
                    <x-icon name="bi-plus-lg" size="sm" /> Add Fish Type
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Reopen the modal the supervisor actually submitted from ──────── --}}
@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('open_edit_modal'))
            ftOpenModal('editModal{{ session('open_edit_modal') }}');
        @else
            ftOpenModal('addModal');
        @endif
    });
</script>
@endif

@endsection

@push('scripts')
<script>
    // ── Modal helpers ────────────────────────────────────────────
    function ftOpenModal(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function ftCloseModal(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
    }
</script>
@endpush
