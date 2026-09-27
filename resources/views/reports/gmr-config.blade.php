@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <!-- Header / Title -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="badge badge-soft badge-primary text-xs font-semibold">RMEC Administration</span>
                <span class="text-xs text-base-content/60">GMR Module</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-base-content sm:text-3xl">GMR Report Configuration</h1>
            <p class="text-sm text-base-content/70">
                Configure printable paper dimensions, orientation, margins, headers, and official signatories for the GMR report.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('gmr.config.preview') }}" target="_blank" class="btn btn-outline btn-secondary gap-2">
                <span class="icon-[tabler--eye] size-4"></span>
                Preview Report
            </a>
            <a href="{{ route('gmr.summary') }}" class="btn btn-outline gap-2">
                <span class="icon-[tabler--arrow-left] size-4"></span>
                GMR Summary
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success shadow-sm">
            <span class="icon-[tabler--circle-check] size-5"></span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error shadow-sm">
            <span class="icon-[tabler--alert-circle] size-5"></span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error shadow-sm">
            <span class="icon-[tabler--alert-circle] size-5"></span>
            <div>
                <span class="font-bold">Please correct the following errors:</span>
                <ul class="mt-1 list-disc list-inside text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- Main Configuration Form -->
    <form action="{{ route('gmr.config.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left 2 Cols: Page Setup & Margins & Headers -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Card 1: Page Setup -->
                <div class="card border border-base-content/20 bg-base-100 shadow-sm">
                    <div class="card-header border-b border-base-content/10 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="icon-[tabler--file-text] size-5 text-primary"></span>
                            <h2 class="card-title text-base font-bold">Paper Size & Orientation</h2>
                        </div>
                    </div>
                    <div class="card-body p-5 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Paper Size -->
                            <div>
                                <label for="paper_size" class="label-text font-medium text-xs uppercase tracking-wider text-base-content/70">
                                    Paper Size <span class="text-error">*</span>
                                </label>
                                <select id="paper_size" name="paper_size" class="select select-bordered w-full mt-1" onchange="toggleCustomPaper(this.value)">
                                    <option value="Long Bond" {{ old('paper_size', $config->paper_size) === 'Long Bond' ? 'selected' : '' }}>
                                        Long Bond / 8.5 × 13 inches (Standard PH Folio)
                                    </option>
                                    <option value="Short Bond" {{ old('paper_size', $config->paper_size) === 'Short Bond' ? 'selected' : '' }}>
                                        Short Bond / 8.5 × 11 inches
                                    </option>
                                    <option value="Letter" {{ old('paper_size', $config->paper_size) === 'Letter' ? 'selected' : '' }}>
                                        Letter / 8.5 × 11 inches
                                    </option>
                                    <option value="A4" {{ old('paper_size', $config->paper_size) === 'A4' ? 'selected' : '' }}>
                                        A4 / 210 × 297 mm
                                    </option>
                                    <option value="Legal" {{ old('paper_size', $config->paper_size) === 'Legal' ? 'selected' : '' }}>
                                        Legal / 8.5 × 14 inches
                                    </option>
                                    <option value="Custom" {{ old('paper_size', $config->paper_size) === 'Custom' ? 'selected' : '' }}>
                                        Custom Dimensions...
                                    </option>
                                </select>
                            </div>

                            <!-- Orientation -->
                            <div>
                                <label class="label-text font-medium text-xs uppercase tracking-wider text-base-content/70">
                                    Orientation <span class="text-error">*</span>
                                </label>
                                <div class="mt-1 grid grid-cols-2 gap-2">
                                    <label class="flex items-center gap-2 rounded-lg border border-base-content/20 p-2.5 cursor-pointer hover:bg-base-200/50">
                                        <input type="radio" name="orientation" value="portrait" class="radio radio-primary radio-sm"
                                            {{ old('orientation', strtolower($config->orientation)) === 'portrait' ? 'checked' : '' }}>
                                        <span class="icon-[tabler--file] size-4"></span>
                                        <span class="text-sm font-medium">Portrait</span>
                                    </label>
                                    <label class="flex items-center gap-2 rounded-lg border border-base-content/20 p-2.5 cursor-pointer hover:bg-base-200/50">
                                        <input type="radio" name="orientation" value="landscape" class="radio radio-primary radio-sm"
                                            {{ old('orientation', strtolower($config->orientation)) === 'landscape' ? 'checked' : '' }}>
                                        <span class="icon-[tabler--file-horizontal] size-4"></span>
                                        <span class="text-sm font-medium">Landscape</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Paper Dimensions Fields -->
                        <div id="custom-paper-container" class="{{ old('paper_size', $config->paper_size) === 'Custom' ? 'block' : 'hidden' }} rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4">
                            <div class="mb-2 text-xs font-semibold uppercase text-primary">Specify Custom Paper Dimensions</div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="custom_width" class="text-xs font-medium text-base-content/70">Width</label>
                                    <input type="number" step="0.01" min="1" max="1000" id="custom_width" name="custom_width"
                                        value="{{ old('custom_width', $config->custom_width) }}" class="input input-bordered input-sm w-full mt-1" placeholder="e.g. 8.5">
                                </div>
                                <div>
                                    <label for="custom_height" class="text-xs font-medium text-base-content/70">Height</label>
                                    <input type="number" step="0.01" min="1" max="1000" id="custom_height" name="custom_height"
                                        value="{{ old('custom_height', $config->custom_height) }}" class="input input-bordered input-sm w-full mt-1" placeholder="e.g. 13.0">
                                </div>
                                <div>
                                    <label for="custom_unit" class="text-xs font-medium text-base-content/70">Unit</label>
                                    <select id="custom_unit" name="custom_unit" class="select select-bordered select-sm w-full mt-1">
                                        <option value="in" {{ old('custom_unit', $config->custom_unit) === 'in' ? 'selected' : '' }}>Inches (in)</option>
                                        <option value="mm" {{ old('custom_unit', $config->custom_unit) === 'mm' ? 'selected' : '' }}>Millimeters (mm)</option>
                                        <option value="cm" {{ old('custom_unit', $config->custom_unit) === 'cm' ? 'selected' : '' }}>Centimeters (cm)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Margins -->
                <div class="card border border-base-content/20 bg-base-100 shadow-sm">
                    <div class="card-header border-b border-base-content/10 px-5 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="icon-[tabler--border-outer] size-5 text-primary"></span>
                                <h2 class="card-title text-base font-bold">Page Margins</h2>
                            </div>
                            <span class="text-xs text-base-content/60">Reference default: 0.50 in</span>
                        </div>
                    </div>
                    <div class="card-body p-5">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <div>
                                <label for="margin_top" class="text-xs font-medium text-base-content/70">Top</label>
                                <input type="number" step="0.01" min="0" max="50" id="margin_top" name="margin_top"
                                    value="{{ old('margin_top', $config->margin_top) }}" class="input input-bordered input-sm w-full mt-1" required>
                            </div>
                            <div>
                                <label for="margin_right" class="text-xs font-medium text-base-content/70">Right</label>
                                <input type="number" step="0.01" min="0" max="50" id="margin_right" name="margin_right"
                                    value="{{ old('margin_right', $config->margin_right) }}" class="input input-bordered input-sm w-full mt-1" required>
                            </div>
                            <div>
                                <label for="margin_bottom" class="text-xs font-medium text-base-content/70">Bottom</label>
                                <input type="number" step="0.01" min="0" max="50" id="margin_bottom" name="margin_bottom"
                                    value="{{ old('margin_bottom', $config->margin_bottom) }}" class="input input-bordered input-sm w-full mt-1" required>
                            </div>
                            <div>
                                <label for="margin_left" class="text-xs font-medium text-base-content/70">Left</label>
                                <input type="number" step="0.01" min="0" max="50" id="margin_left" name="margin_left"
                                    value="{{ old('margin_left', $config->margin_left) }}" class="input input-bordered input-sm w-full mt-1" required>
                            </div>
                            <div>
                                <label for="margin_unit" class="text-xs font-medium text-base-content/70">Unit</label>
                                <select id="margin_unit" name="margin_unit" class="select select-bordered select-sm w-full mt-1">
                                    <option value="in" {{ old('margin_unit', $config->margin_unit) === 'in' ? 'selected' : '' }}>Inches (in)</option>
                                    <option value="mm" {{ old('margin_unit', $config->margin_unit) === 'mm' ? 'selected' : '' }}>Millimeters (mm)</option>
                                    <option value="cm" {{ old('margin_unit', $config->margin_unit) === 'cm' ? 'selected' : '' }}>Centimeters (cm)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Header & Title Texts -->
                <div class="card border border-base-content/20 bg-base-100 shadow-sm">
                    <div class="card-header border-b border-base-content/10 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="icon-[tabler--heading] size-5 text-primary"></span>
                            <h2 class="card-title text-base font-bold">Report Header & Titles</h2>
                        </div>
                    </div>
                    <div class="card-body p-5 space-y-3">
                        <div>
                            <label for="title" class="text-xs font-medium text-base-content/70 uppercase tracking-wider">Report Title <span class="text-error">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title', $config->title) }}" class="input input-bordered w-full mt-1 text-sm font-semibold" required>
                        </div>
                        <div>
                            <label for="subtitle" class="text-xs font-medium text-base-content/70 uppercase tracking-wider">Subtitle <span class="text-error">*</span></label>
                            <input type="text" id="subtitle" name="subtitle" value="{{ old('subtitle', $config->subtitle) }}" class="input input-bordered w-full mt-1 text-sm" required>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="region_text" class="text-xs font-medium text-base-content/70 uppercase tracking-wider">Region Line <span class="text-error">*</span></label>
                                <input type="text" id="region_text" name="region_text" value="{{ old('region_text', $config->region_text) }}" class="input input-bordered w-full mt-1 text-sm" required>
                            </div>
                            <div>
                                <label for="branch_text" class="text-xs font-medium text-base-content/70 uppercase tracking-wider">Branch Line (Optional Override)</label>
                                <input type="text" id="branch_text" name="branch_text" value="{{ old('branch_text', $config->branch_text) }}" placeholder="e.g. North Cotabato Branch" class="input input-bordered w-full mt-1 text-sm">
                                <span class="text-[11px] text-base-content/50">Leave blank to dynamically use the selected records' branch.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right 1 Col: Summary & Quick Actions -->
            <div class="space-y-6">
                <div class="card border border-base-content/20 bg-base-100 shadow-sm sticky top-20">
                    <div class="card-header border-b border-base-content/10 px-5 py-4">
                        <h2 class="card-title text-base font-bold">Configuration Status</h2>
                    </div>
                    <div class="card-body p-5 space-y-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between py-1 border-b border-base-content/10">
                                <span class="text-base-content/60">Paper Size:</span>
                                <span class="font-bold">{{ $config->paper_size }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-content/10">
                                <span class="text-base-content/60">Orientation:</span>
                                <span class="font-bold">{{ ucfirst($config->orientation) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-content/10">
                                <span class="text-base-content/60">Margins:</span>
                                <span class="font-medium">{{ $config->getCssMargins() }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-base-content/10">
                                <span class="text-base-content/60">Active Signatories:</span>
                                <span class="font-bold badge badge-soft badge-primary">{{ $signatories->where('is_active', true)->count() }}</span>
                            </div>
                        </div>

                        <div class="pt-2 flex flex-col gap-2">
                            <button type="submit" class="btn btn-primary w-full gap-2">
                                <span class="icon-[tabler--device-floppy] size-5"></span>
                                Save Configuration
                            </button>
                            <a href="{{ route('gmr.config.preview') }}" target="_blank" class="btn btn-outline btn-secondary w-full gap-2">
                                <span class="icon-[tabler--eye] size-5"></span>
                                Preview Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Signatories Management Section -->
    <div class="card border border-base-content/20 bg-base-100 shadow-sm mt-8">
        <div class="card-header border-b border-base-content/10 px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="icon-[tabler--signature] size-5 text-primary"></span>
                    <h2 class="card-title text-base font-bold">Signatories Management</h2>
                </div>
                <p class="text-xs text-base-content/60 mt-0.5">
                    Add, edit, reorder, or deactivate official signatories. Only active signatories appear on printed reports.
                </p>
            </div>
            <button type="button" onclick="document.getElementById('modal-add-signatory').showModal()" class="btn btn-primary btn-sm gap-2">
                <span class="icon-[tabler--plus] size-4"></span>
                Add Signatory
            </button>
        </div>

        <div class="card-body p-0 overflow-x-auto">
            <table class="table table-hover w-full">
                <thead>
                    <tr class="bg-base-200/50 text-xs uppercase tracking-wider text-base-content/70">
                        <th class="w-12 text-center">Order</th>
                        <th>Signatory Name</th>
                        <th>Position / Designation</th>
                        <th>Role / Group</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-content/10 text-sm">
                    @forelse ($signatories as $signatory)
                        <tr class="{{ $signatory->is_active ? '' : 'opacity-60 bg-base-200/20' }}">
                            <td class="text-center font-bold text-base-content/70">
                                {{ $signatory->display_order }}
                            </td>
                            <td class="font-bold text-base-content">
                                {{ $signatory->name }}
                            </td>
                            <td class="italic text-base-content/80">
                                {{ $signatory->position }}
                            </td>
                            <td>
                                @switch($signatory->role_group)
                                    @case('member')
                                        <span class="badge badge-soft badge-info text-xs">RMEC Member</span>
                                        @break
                                    @case('chairperson')
                                        <span class="badge badge-soft badge-primary text-xs">Chairperson</span>
                                        @break
                                    @case('coa')
                                        <span class="badge badge-soft badge-secondary text-xs">COA Representative</span>
                                        @break
                                    @case('reviewer')
                                        <span class="badge badge-soft badge-warning text-xs">Reviewer</span>
                                        @break
                                    @default
                                        <span class="badge badge-soft badge-neutral text-xs">{{ ucfirst($signatory->role_group ?? 'Signatory') }}</span>
                                @endswitch
                            </td>
                            <td class="text-center">
                                @if ($signatory->is_active)
                                    <span class="badge badge-success badge-sm text-xs font-medium">Active</span>
                                @else
                                    <span class="badge badge-neutral badge-sm text-xs font-medium">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end pr-5">
                                <div class="inline-flex items-center gap-1">
                                    <!-- Toggle Active/Inactive -->
                                    <form action="{{ route('gmr.config.signatories.toggle', $signatory) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-ghost btn-xs" title="{{ $signatory->is_active ? 'Deactivate' : 'Activate' }}">
                                            <span class="icon-[tabler--toggle-{{ $signatory->is_active ? 'right text-success' : 'left text-base-content/40' }}] size-4"></span>
                                        </button>
                                    </form>

                                    <!-- Edit Button -->
                                    <button type="button" onclick="openEditModal({{ json_encode($signatory) }})" class="btn btn-ghost btn-xs text-primary" title="Edit Signatory">
                                        <span class="icon-[tabler--pencil] size-4"></span>
                                    </button>

                                    <!-- Delete Button -->
                                    <form action="{{ route('gmr.config.signatories.destroy', $signatory) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this signatory?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error" title="Delete Signatory">
                                            <span class="icon-[tabler--trash] size-4"></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-base-content/60">
                                No signatories configured yet. Click "Add Signatory" to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Signatory Modal -->
<dialog id="modal-add-signatory" class="modal">
    <div class="modal-box max-w-md">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="text-lg font-bold">Add Official Signatory</h3>
        <p class="text-xs text-base-content/60 mt-1">Configure an official signatory to appear on the printed GMR report.</p>

        <form action="{{ route('gmr.config.signatories.store') }}" method="POST" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="new_name" class="text-xs font-semibold text-base-content/80">Full Name <span class="text-error">*</span></label>
                <input type="text" id="new_name" name="name" class="input input-bordered w-full mt-1" placeholder="e.g. DINDO O. QUITOR" required>
            </div>

            <div>
                <label for="new_position" class="text-xs font-semibold text-base-content/80">Position / Designation <span class="text-error">*</span></label>
                <input type="text" id="new_position" name="position" class="input input-bordered w-full mt-1" placeholder="e.g. Regional Economist" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="new_role_group" class="text-xs font-semibold text-base-content/80">Role / Group</label>
                    <select id="new_role_group" name="role_group" class="select select-bordered w-full mt-1">
                        <option value="member">RMEC Member</option>
                        <option value="chairperson">Chairperson</option>
                        <option value="coa">COA Representative</option>
                        <option value="reviewer">Reviewer</option>
                    </select>
                </div>
                <div>
                    <label for="new_display_order" class="text-xs font-semibold text-base-content/80">Display Order <span class="text-error">*</span></label>
                    <input type="number" id="new_display_order" name="display_order" value="{{ ($signatories->max('display_order') ?? 0) + 1 }}" min="1" max="999" class="input input-bordered w-full mt-1" required>
                </div>
            </div>

            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-primary" checked>
                    <span class="label-text text-sm">Active (Include in printed report)</span>
                </label>
            </div>

            <div class="modal-action">
                <button type="button" onclick="document.getElementById('modal-add-signatory').close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Signatory</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Signatory Modal -->
<dialog id="modal-edit-signatory" class="modal">
    <div class="modal-box max-w-md">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="text-lg font-bold">Edit Signatory</h3>
        <p class="text-xs text-base-content/60 mt-1">Update signatory details or display order.</p>

        <form id="edit-signatory-form" method="POST" class="mt-4 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="edit_name" class="text-xs font-semibold text-base-content/80">Full Name <span class="text-error">*</span></label>
                <input type="text" id="edit_name" name="name" class="input input-bordered w-full mt-1" required>
            </div>

            <div>
                <label for="edit_position" class="text-xs font-semibold text-base-content/80">Position / Designation <span class="text-error">*</span></label>
                <input type="text" id="edit_position" name="position" class="input input-bordered w-full mt-1" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="edit_role_group" class="text-xs font-semibold text-base-content/80">Role / Group</label>
                    <select id="edit_role_group" name="role_group" class="select select-bordered w-full mt-1">
                        <option value="member">RMEC Member</option>
                        <option value="chairperson">Chairperson</option>
                        <option value="coa">COA Representative</option>
                        <option value="reviewer">Reviewer</option>
                    </select>
                </div>
                <div>
                    <label for="edit_display_order" class="text-xs font-semibold text-base-content/80">Display Order <span class="text-error">*</span></label>
                    <input type="number" id="edit_display_order" name="display_order" min="1" max="999" class="input input-bordered w-full mt-1" required>
                </div>
            </div>

            <div class="form-control">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="checkbox checkbox-primary">
                    <span class="label-text text-sm">Active (Include in printed report)</span>
                </label>
            </div>

            <div class="modal-action">
                <button type="button" onclick="document.getElementById('modal-edit-signatory').close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Signatory</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function toggleCustomPaper(val) {
        const container = document.getElementById('custom-paper-container');
        if (val === 'Custom') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }

    function openEditModal(signatory) {
        const form = document.getElementById('edit-signatory-form');
        form.action = `/gmr/config/signatories/${signatory.id}`;

        document.getElementById('edit_name').value = signatory.name;
        document.getElementById('edit_position').value = signatory.position;
        document.getElementById('edit_role_group').value = signatory.role_group || 'member';
        document.getElementById('edit_display_order').value = signatory.display_order;
        document.getElementById('edit_is_active').checked = !!signatory.is_active;

        document.getElementById('modal-edit-signatory').showModal();
    }
</script>
@endsection
