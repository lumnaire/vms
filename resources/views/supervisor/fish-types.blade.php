@extends('layouts.app')

@section('title', 'Fish Type Management')
@section('subtitle', 'Add, rename, or deactivate fish types available in the market')

@push('styles')
<style>
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

    .btn-ft-warn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 7px; font-size: 12px; font-weight: 600;
        background: #fff; border: 1px solid #fde68a; color: #92400e; cursor: pointer;
        transition: all 0.12s;
    }
    .btn-ft-warn:hover { background: #fffbeb; border-color: #fbbf24; }

    .btn-ft-activate {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 12px; border-radius: 7px; font-size: 12px; font-weight: 600;
        background: #fff; border: 1px solid #bbf7d0; color: #15803d; cursor: pointer;
        transition: all 0.12s;
    }
    .btn-ft-activate:hover { background: #f0fdf4; border-color: #4ade80; }

    .btn-ft-delete {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 10px; border-radius: 7px; font-size: 12px; font-weight: 600;
        background: #fff; border: 1px solid #fecaca; color: #dc2626; cursor: pointer;
        transition: all 0.12s;
    }
    .btn-ft-delete:hover { background: #fef2f2; border-color: #f87171; }

    /* Modal */
    .ft-modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.45);
        z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 16px;
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
    .ft-btn-danger {
        padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
        background: #dc2626; color: #fff; border: none; cursor: pointer;
        transition: background 0.15s;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .ft-btn-danger:hover { background: #b91c1c; }
    .ft-btn-danger:disabled { background: #fca5a5; cursor: not-allowed; }
    .ft-btn-warn-solid {
        padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 600;
        background: #d97706; color: #fff; border: none; cursor: pointer;
        transition: background 0.15s;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .ft-btn-warn-solid:hover { background: #b45309; }

    /* Image upload */
    .ft-img-dropzone {
        border: 2px dashed #e2e8f0; border-radius: 10px;
        padding: 14px 12px; text-align: center; cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
        background: #fafafa; position: relative;
    }
    .ft-img-dropzone:hover { border-color: #93c5fd; background: #f0f9ff; }
    .ft-img-dropzone input[type=file] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
    }
    .ft-img-preview {
        width: 72px; height: 72px; border-radius: 8px; object-fit: cover;
        border: 2px solid #e2e8f0; display: block; margin: 0 auto 8px;
    }
    .ft-img-thumb {
        width: 32px; height: 32px; border-radius: 8px; object-fit: cover;
        border: 1px solid #e2e8f0; flex-shrink: 0;
    }
</style>
@endpush

@section('content')

{{-- ── Page Header ──────────────────────────────────────────── --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-slate-800 font-bold" style="font-size: 20px;">Fish Type Management</h1>
        <p class="text-slate-400 mt-0.5" style="font-size: 12.5px;">Add, rename, or deactivate fish types available in the market.</p>
    </div>
    <button class="btn-ft-primary" onclick="ftOpenModal('addModal')">
        <i class="bi bi-plus-lg"></i> Add Fish Type
    </button>
</div>

{{-- ── Flash Messages ───────────────────────────────────────── --}}
@if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-4"
         style="background:#f0fdf4; border:1px solid #bbf7d0;">
        <i class="bi bi-check-circle-fill text-emerald-500" style="font-size:15px;"></i>
        <p class="text-emerald-700 font-semibold" style="font-size:13px;">{{ session('success') }}</p>
    </div>
@endif
@if(session('error'))
    <div class="flex items-center gap-3 px-4 py-3 rounded-xl mb-4"
         style="background:#fef2f2; border:1px solid #fecaca;">
        <i class="bi bi-exclamation-circle-fill text-rose-500" style="font-size:15px;"></i>
        <p class="text-rose-700 font-semibold" style="font-size:13px;">{{ session('error') }}</p>
    </div>
@endif

{{-- ── Stat Cards ───────────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-4 mb-6" style="max-width: 400px;">
    <div class="bg-white rounded-xl p-4 border border-slate-100" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold" style="font-size:10.5px;text-transform:uppercase;letter-spacing:0.07em;">Active</p>
        <p class="text-blue-600 font-bold mt-1" style="font-size:30px;line-height:1;">{{ $totalActive }}</p>
        <p class="text-slate-400 mt-1" style="font-size:11px;">Fish types in use</p>
    </div>
    <div class="bg-white rounded-xl p-4 border border-slate-100" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <p class="text-slate-400 font-semibold" style="font-size:10.5px;text-transform:uppercase;letter-spacing:0.07em;">Inactive</p>
        <p class="text-slate-400 font-bold mt-1" style="font-size:30px;line-height:1;">{{ $totalInactive }}</p>
        <p class="text-slate-400 mt-1" style="font-size:11px;">Deactivated types</p>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <p class="text-slate-700 font-bold" style="font-size:13.5px;">All Fish Types</p>
        <span class="text-slate-400" style="font-size:11.5px;">{{ $fishTypes->count() }} total</span>
    </div>

    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc; border-bottom:1px solid #f1f5f9;">
                <th class="text-left text-slate-400 font-semibold px-5 py-3" style="font-size:11px;text-transform:uppercase;letter-spacing:0.07em;width:50px;">#</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3" style="font-size:11px;text-transform:uppercase;letter-spacing:0.07em;">Fish Type Name</th>
                <th class="text-left text-slate-400 font-semibold px-4 py-3" style="font-size:11px;text-transform:uppercase;letter-spacing:0.07em;width:120px;">Status</th>
                <th class="text-right text-slate-400 font-semibold px-5 py-3" style="font-size:11px;text-transform:uppercase;letter-spacing:0.07em;width:220px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fishTypes as $index => $ft)
            <tr class="ft-table-row" style="border-bottom:1px solid #f1f5f9;">
                <td class="px-5 py-3 text-slate-400 font-medium" style="font-size:13px;">{{ $index + 1 }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        @if($ft->image_path)
                            <img src="{{ asset('storage/' . $ft->image_path) }}"
                                 alt="{{ $ft->name }}"
                                 class="ft-img-thumb"
                                 style="{{ $ft->is_active ? '' : 'opacity:0.45;' }}">
                        @else
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                 style="background:{{ $ft->is_active ? 'linear-gradient(135deg,#0f2d5e,#1d4ed8)' : '#f1f5f9' }};">
                                <i class="bi bi-fish" style="font-size:13px; color:{{ $ft->is_active ? '#fff' : '#94a3b8' }};"></i>
                            </div>
                        @endif
                        <span class="font-semibold" style="font-size:13.5px; color:{{ $ft->is_active ? '#334155' : '#94a3b8' }};">
                            {{ $ft->name }}
                        </span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    @if($ft->is_active)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                              style="font-size:11px;background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;">
                            <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                              style="font-size:11px;background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;">
                            <span style="width:6px;height:6px;border-radius:50%;background:#cbd5e1;display:inline-block;"></span>
                            Inactive
                        </span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        {{-- Edit --}}
                        <button class="btn-ft-outline"
                                onclick="ftOpenModal('editModal{{ $ft->id }}')">
                            <i class="bi bi-pencil" style="font-size:11px;"></i> Edit
                        </button>

                        {{-- Deactivate (active) --}}
                        @if($ft->is_active)
                            <button class="btn-ft-warn"
                                    onclick="ftOpenDeactivate(
                                        '{{ route('supervisor.fish-types.toggle', $ft) }}',
                                        '{{ addslashes($ft->name) }}'
                                    )">
                                <i class="bi bi-slash-circle" style="font-size:11px;"></i> Deactivate
                            </button>

                        {{-- Activate + Delete (inactive) --}}
                        @else
                            <button class="btn-ft-activate"
                                    onclick="ftOpenActivate(
                                        '{{ route('supervisor.fish-types.toggle', $ft) }}',
                                        '{{ addslashes($ft->name) }}'
                                    )">
                                <i class="bi bi-check-circle" style="font-size:11px;"></i> Activate
                            </button>
                            <button class="btn-ft-delete"
                                    onclick="ftOpenDelete(
                                        '{{ route('supervisor.fish-types.destroy', $ft) }}',
                                        '{{ addslashes($ft->name) }}'
                                    )">
                                <i class="bi bi-trash-fill" style="font-size:11px;"></i>
                            </button>
                        @endif
                    </div>
                </td>
            </tr>

            {{-- ── Edit Modal (per row) ──────────────────────────── --}}
            <div id="editModal{{ $ft->id }}" class="ft-modal-overlay" style="display:none;"
                 onclick="if(event.target===this) ftCloseModal('editModal{{ $ft->id }}')">
                <div class="ft-modal-box">
                    <form action="{{ route('supervisor.fish-types.update', $ft) }}" method="POST" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <div class="ft-modal-header">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                     style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8);">
                                    <i class="bi bi-pencil text-white" style="font-size:12px;"></i>
                                </div>
                                <div>
                                    <p class="text-slate-800 font-bold" style="font-size:14px;">Edit Fish Type</p>
                                    <p class="text-slate-400" style="font-size:11px;">Update the name or photo of this fish type</p>
                                </div>
                            </div>
                            <button type="button" onclick="ftCloseModal('editModal{{ $ft->id }}')"
                                    style="border:none;background:transparent;cursor:pointer;color:#94a3b8;padding:4px;">
                                <i class="bi bi-x-lg" style="font-size:13px;"></i>
                            </button>
                        </div>
                        <div class="ft-modal-body">
                            <label class="ft-form-label">Fish Type Name</label>
                            <input type="text" name="name" class="ft-form-input"
                                   value="{{ $ft->name }}" required maxlength="100">

                            <label class="ft-form-label mt-3" style="margin-top:14px;">Photo <span class="font-normal text-slate-400">(optional)</span></label>
                            <div class="ft-img-dropzone" id="editDrop{{ $ft->id }}">
                                <input type="file" name="image" accept="image/jpg,image/jpeg,image/png,image/webp"
                                       onchange="ftPreviewImg(this, 'editPrev{{ $ft->id }}', 'editHint{{ $ft->id }}')">
                                @if($ft->image_path)
                                    <img id="editPrev{{ $ft->id }}"
                                         class="ft-img-preview"
                                         src="{{ asset('storage/' . $ft->image_path) }}"
                                         alt="{{ $ft->name }}">
                                    <p id="editHint{{ $ft->id }}" class="text-slate-400" style="font-size:11px;">Click to replace photo</p>
                                @else
                                    <img id="editPrev{{ $ft->id }}" class="ft-img-preview" style="display:none;" src="" alt="">
                                    <i class="bi bi-image text-slate-300" style="font-size:22px; display:block; margin-bottom:4px;"></i>
                                    <p id="editHint{{ $ft->id }}" class="text-slate-400" style="font-size:11px;">Click to upload a photo</p>
                                @endif
                                <p class="text-slate-300 mt-1" style="font-size:10.5px;">JPG, PNG, WEBP · max 2 MB</p>
                            </div>
                            @if($ft->image_path)
                                <label class="flex items-center gap-2 mt-2 cursor-pointer" style="font-size:12px; color:#ef4444;">
                                    <input type="checkbox" name="remove_image" value="1"
                                           onchange="ftToggleRemoveImg(this, 'editPrev{{ $ft->id }}', 'editHint{{ $ft->id }}')">
                                    Remove current photo
                                </label>
                            @endif
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
                <td colspan="4" class="text-center py-16">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mb-1"
                             style="background:#eff6ff;">
                            <i class="bi bi-fish text-blue-400" style="font-size:22px;"></i>
                        </div>
                        <p class="text-slate-500 font-semibold" style="font-size:13px;">No fish types found</p>
                        <p class="text-slate-400" style="font-size:12px;">Add one using the button above.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════════════
     SHARED MODALS (single instance, populated via JS)
     ══════════════════════════════════════════════════════════════ --}}

{{-- ── Add Fish Type Modal ──────────────────────────────────── --}}
<div id="addModal" class="ft-modal-overlay" style="display:none;"
     onclick="if(event.target===this) ftCloseModal('addModal')">
    <div class="ft-modal-box">
        <form action="{{ route('supervisor.fish-types.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="ft-modal-header">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                         style="background:linear-gradient(135deg,#0f2d5e,#1d4ed8);">
                        <i class="bi bi-plus-lg text-white" style="font-size:13px;"></i>
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold" style="font-size:14px;">Add New Fish Type</p>
                        <p class="text-slate-400" style="font-size:11px;">Enter the name and optional photo</p>
                    </div>
                </div>
                <button type="button" onclick="ftCloseModal('addModal')"
                        style="border:none;background:transparent;cursor:pointer;color:#94a3b8;padding:4px;">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>
            <div class="ft-modal-body">
                @error('name')
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg mb-3"
                         style="background:#fef2f2;border:1px solid #fecaca;">
                        <i class="bi bi-exclamation-circle-fill text-rose-400" style="font-size:13px;"></i>
                        <p class="text-rose-600 font-semibold" style="font-size:12px;">{{ $message }}</p>
                    </div>
                @enderror
                <label class="ft-form-label">Fish Type Name</label>
                <input type="text" name="name"
                       class="ft-form-input @error('name') border-rose-300 @enderror"
                       value="{{ old('name') }}"
                       placeholder="e.g. Bangus, Tilapia, Alumahan"
                       required maxlength="100">
                <p class="text-slate-400 mt-2" style="font-size:11.5px;">
                    <i class="bi bi-info-circle"></i> Name will be auto-formatted to Title Case.
                </p>

                <label class="ft-form-label" style="margin-top:14px;">Photo <span class="font-normal text-slate-400">(optional)</span></label>
                <div class="ft-img-dropzone">
                    <input type="file" name="image" accept="image/jpg,image/jpeg,image/png,image/webp"
                           onchange="ftPreviewImg(this, 'addPrev', 'addHint')">
                    <img id="addPrev" class="ft-img-preview" style="display:none;" src="" alt="">
                    <i class="bi bi-image text-slate-300" id="addIcon" style="font-size:22px; display:block; margin-bottom:4px;"></i>
                    <p id="addHint" class="text-slate-400" style="font-size:11px;">Click to upload a photo</p>
                    <p class="text-slate-300 mt-1" style="font-size:10.5px;">JPG, PNG, WEBP · max 2 MB</p>
                </div>
            </div>
            <div class="ft-modal-footer">
                <button type="button" class="ft-btn-cancel" onclick="ftCloseModal('addModal')">Cancel</button>
                <button type="submit" class="ft-btn-save">
                    <i class="bi bi-plus-lg" style="font-size:12px;"></i> Add Fish Type
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Deactivate Confirm Modal ─────────────────────────────── --}}
<div id="deactivateModal" class="ft-modal-overlay" style="display:none;"
     onclick="if(event.target===this) ftCloseModal('deactivateModal')">
    <div class="ft-modal-box">
        <form id="deactivate-form" method="POST">
            @csrf @method('PATCH')
            <div class="ft-modal-header" style="border-bottom:1px solid #fef9c3;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:#fefce8; border:1px solid #fde68a;">
                        <i class="bi bi-slash-circle-fill" style="font-size:16px; color:#d97706;"></i>
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold" style="font-size:14px;">Deactivate Fish Type</p>
                        <p class="text-slate-400" style="font-size:11px;">It will be hidden from all dropdowns</p>
                    </div>
                </div>
                <button type="button" onclick="ftCloseModal('deactivateModal')"
                        style="border:none;background:transparent;cursor:pointer;color:#94a3b8;padding:4px;">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>
            <div class="ft-modal-body">
                <div class="rounded-xl p-4" style="background:#fefce8; border:1px solid #fde68a;">
                    <p class="font-semibold" style="font-size:13px; color:#92400e;">
                        Are you sure you want to deactivate
                        <span id="deactivate-name" class="font-bold"></span>?
                    </p>
                    <p class="mt-1" style="font-size:11.5px; color:#a16207;">
                        Vendors won't be able to submit entries for this fish type until it's reactivated.
                    </p>
                </div>
            </div>
            <div class="ft-modal-footer">
                <button type="button" class="ft-btn-cancel" onclick="ftCloseModal('deactivateModal')">Cancel</button>
                <button type="submit" class="ft-btn-warn-solid">
                    <i class="bi bi-slash-circle" style="font-size:12px;"></i> Yes, Deactivate
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Activate Confirm Modal ───────────────────────────────── --}}
<div id="activateModal" class="ft-modal-overlay" style="display:none;"
     onclick="if(event.target===this) ftCloseModal('activateModal')">
    <div class="ft-modal-box">
        <form id="activate-form" method="POST">
            @csrf @method('PATCH')
            <div class="ft-modal-header" style="border-bottom:1px solid #dcfce7;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:#f0fdf4; border:1px solid #bbf7d0;">
                        <i class="bi bi-check-circle-fill" style="font-size:16px; color:#16a34a;"></i>
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold" style="font-size:14px;">Activate Fish Type</p>
                        <p class="text-slate-400" style="font-size:11px;">It will be visible in all dropdowns again</p>
                    </div>
                </div>
                <button type="button" onclick="ftCloseModal('activateModal')"
                        style="border:none;background:transparent;cursor:pointer;color:#94a3b8;padding:4px;">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>
            <div class="ft-modal-body">
                <div class="rounded-xl p-4" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                    <p class="font-semibold" style="font-size:13px; color:#14532d;">
                        Reactivate <span id="activate-name" class="font-bold"></span>?
                    </p>
                    <p class="mt-1" style="font-size:11.5px; color:#15803d;">
                        Vendors will be able to submit inventory entries for this fish type again.
                    </p>
                </div>
            </div>
            <div class="ft-modal-footer">
                <button type="button" class="ft-btn-cancel" onclick="ftCloseModal('activateModal')">Cancel</button>
                <button type="submit" class="ft-btn-save" style="background:#16a34a;">
                    <i class="bi bi-check-circle" style="font-size:12px;"></i> Yes, Activate
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Delete Confirm Modal (type CONFIRM) ─────────────────── --}}
<div id="deleteModal" class="ft-modal-overlay" style="display:none;"
     onclick="if(event.target===this) ftCloseModal('deleteModal')">
    <div class="ft-modal-box">
        <form id="delete-form" method="POST">
            @csrf @method('DELETE')
            <div class="ft-modal-header" style="border-bottom:1px solid #fef2f2;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:#fef2f2; border:1px solid #fecaca;">
                        <i class="bi bi-trash-fill" style="font-size:15px; color:#dc2626;"></i>
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold" style="font-size:14px;">Permanently Delete</p>
                        <p class="text-slate-400" style="font-size:11px;">This cannot be undone</p>
                    </div>
                </div>
                <button type="button" onclick="ftCloseModal('deleteModal')"
                        style="border:none;background:transparent;cursor:pointer;color:#94a3b8;padding:4px;">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>
            <div class="ft-modal-body">
                <div class="rounded-xl p-4 mb-4" style="background:#fef2f2; border:1px solid #fecaca;">
                    <p class="font-semibold" style="font-size:13px; color:#991b1b;">
                        You are about to permanently delete
                        <span id="delete-name" class="font-bold"></span>.
                    </p>
                    <p class="mt-1" style="font-size:11.5px; color:#b91c1c;">
                        All associated price guides and historical data for this fish type may be affected.
                        This action is <strong>irreversible</strong>.
                    </p>
                </div>
                <label class="ft-form-label" style="color:#dc2626;">
                    Type <span class="font-mono font-bold" style="background:#fef2f2; padding:1px 6px; border-radius:4px; border:1px solid #fecaca;">CONFIRM</span> to delete
                </label>
                <input type="text" id="delete-confirm-input" class="ft-form-input danger"
                       placeholder="Type CONFIRM here" autocomplete="off">
            </div>
            <div class="ft-modal-footer">
                <button type="button" class="ft-btn-cancel" onclick="ftCloseModal('deleteModal')">Cancel</button>
                <button type="submit" id="delete-submit-btn" class="ft-btn-danger" disabled>
                    <i class="bi bi-trash-fill" style="font-size:12px;"></i> Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Auto-open Add modal on validation error --}}
@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        ftOpenModal('addModal');
    });
</script>
@endif

@endsection

@push('scripts')
<script>
    // ── Image preview helper ─────────────────────────────────────
    function ftPreviewImg(input, previewId, hintId) {
        const prev = document.getElementById(previewId);
        const hint = document.getElementById(hintId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                prev.src = e.target.result;
                prev.style.display = 'block';
                if (hint) hint.textContent = 'Click to replace photo';
                // hide the upload icon for the add modal
                const icon = document.getElementById('addIcon');
                if (icon) icon.style.display = 'none';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ── Remove image checkbox toggle ────────────────────────────
    function ftToggleRemoveImg(checkbox, previewId, hintId) {
        const prev = document.getElementById(previewId);
        const hint = document.getElementById(hintId);
        if (checkbox.checked) {
            prev.style.opacity = '0.25';
            if (hint) hint.textContent = 'Photo will be removed on save';
        } else {
            prev.style.opacity = '1';
            if (hint) hint.textContent = 'Click to replace photo';
        }
    }

    // ── Modal helpers ────────────────────────────────────────────
    function ftOpenModal(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function ftCloseModal(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
    }

    // ── Deactivate modal ─────────────────────────────────────────
    function ftOpenDeactivate(actionUrl, name) {
        document.getElementById('deactivate-form').action = actionUrl;
        document.getElementById('deactivate-name').textContent = name;
        ftOpenModal('deactivateModal');
    }

    // ── Activate modal ───────────────────────────────────────────
    function ftOpenActivate(actionUrl, name) {
        document.getElementById('activate-form').action = actionUrl;
        document.getElementById('activate-name').textContent = name;
        ftOpenModal('activateModal');
    }

    // ── Delete modal ─────────────────────────────────────────────
    function ftOpenDelete(actionUrl, name) {
        document.getElementById('delete-form').action = actionUrl;
        document.getElementById('delete-name').textContent = name;
        document.getElementById('delete-confirm-input').value = '';
        document.getElementById('delete-submit-btn').disabled = true;
        ftOpenModal('deleteModal');
    }

    // Enable delete button only when "CONFIRM" is typed exactly
    document.getElementById('delete-confirm-input').addEventListener('input', function () {
        document.getElementById('delete-submit-btn').disabled = this.value !== 'CONFIRM';
    });
</script>
@endpush