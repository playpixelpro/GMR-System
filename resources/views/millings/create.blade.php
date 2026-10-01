@extends('layouts.app')

@section('title', 'Assign Rice Milling')

@section('content')
<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
    <div>
        <h1 class="text-2xl font-bold leading-tight text-black sm:text-3xl">Assign Rice Milling</h1>
        <p class="mt-2 text-sm leading-6 text-black">Assign a rice milling to a single pile with an approved GMR. The pile's volume is frozen as the milling target.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('millings.index') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
            <span class="icon-[tabler--arrow-left] size-5"></span>
            Back
        </a>
    </div>
</div>

<form method="POST" action="{{ route('millings.store') }}" class="card border border-base-content/10 bg-base-100 p-5 text-black shadow-sm max-w-2xl">
    @csrf

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Branch *</span>
            <select name="branch_id" id="branch-select" class="select select-bordered min-h-11 w-full text-base text-black" required onchange="filterPiles()">
                <option value="">Select branch</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) ($selected_branch_id ?? 0) === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Pile (approved GMR only) *</span>
            <select name="pile_id" id="pile-select" class="select select-bordered min-h-11 w-full text-base text-black" required onchange="updatePileInfo()">
                <option value="">Select branch first</option>
                @foreach ($piles as $pile)
                    @php
                        $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
                        $volumeBags = $pile->volume_kg !== null ? round((float) $pile->volume_kg / 50, 3) : null;
                        $pileLabel = ($pile->pile_number ?? ($pile->number ?? '#'.$pile->id))
                            . ' · ' . ($pile->warehouse?->name ?? '—')
                            . ($volumeBags !== null ? ' · ' . number_format($volumeBags, 3) . ' bags' : '');
                    @endphp
                    <option value="{{ $pile->id }}" data-branch-id="{{ $pileBranchId }}" data-warehouse="{{ $pile->warehouse?->name ?? '—' }}" data-volume="{{ $volumeBags !== null ? number_format($volumeBags, 3) . ' bags' : 'N/A' }}" data-variety="{{ $pile->variety ?? '' }}" data-quality="{{ $pile->quality ?? '' }}">
                        {{ $pileLabel }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Miller / Rice Mill</span>
            <x-miller-combobox name="miller" class="input input-bordered min-h-11 w-full text-black" placeholder="e.g. North Cotabato Rice Mill" />
        </label>

        <label class="form-control">
            <span class="label-text mb-2 text-sm font-semibold text-black">Project / Memo Reference No. *</span>
            <input type="text" name="reference_number" class="input input-bordered min-h-11 w-full text-black" placeholder="Bidding project ref. no. or NFA memo no." required />
            <span class="mt-1 text-xs text-base-content/60">For contracted millers: the bidding project reference. For NFA-owned rice mills: the memorandum no.</span>
            @error('reference_number') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <div id="pile-info" class="mt-4 hidden rounded-lg border border-base-content/10 bg-base-200/50 p-3 text-sm">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div><span class="text-xs font-semibold uppercase text-base-content/60">Warehouse</span><span id="info-warehouse" class="block font-medium">—</span></div>
            <div><span class="text-xs font-semibold uppercase text-base-content/60">Volume (bags)</span><span id="info-volume" class="block font-medium font-mono">—</span></div>
            <div><span class="text-xs font-semibold uppercase text-base-content/60">Variety</span><span id="info-variety" class="block font-medium">—</span></div>
            <div><span class="text-xs font-semibold uppercase text-base-content/60">Quality</span><span id="info-quality" class="block font-medium">—</span></div>
        </div>
    </div>

    <label class="form-control mt-4">
        <span class="label-text mb-2 text-sm font-semibold text-black">Remarks</span>
        <textarea name="remarks" class="textarea textarea-bordered min-h-24 text-black" placeholder="Optional notes about this milling assignment"></textarea>
    </label>

    @if ($piles->isEmpty())
        <x-alert-box type="warning" message="No piles with an approved GMR are currently available for milling in the selected branch." class="mt-4" />
    @endif

    <div class="mt-5">
        <button type="submit" class="btn btn-primary gap-2" @disabled($piles->isEmpty())>
            <span class="icon-[tabler--check] size-5"></span>
            Assign Milling
        </button>
    </div>
</form>

<script>
    function filterPiles() {
        const branchId = document.getElementById('branch-select').value;
        const pileSelect = document.getElementById('pile-select');
        const options = pileSelect.querySelectorAll('option[data-branch-id]');

        let anyVisible = false;
        options.forEach(opt => {
            const match = !branchId || String(opt.dataset.branchId) === String(branchId);
            opt.hidden = !match;
            if (match && opt.value) anyVisible = true;
        });

        const placeholder = pileSelect.querySelector('option:not([data-branch-id])');
        if (placeholder) {
            placeholder.textContent = branchId ? (anyVisible ? 'Select a pile' : 'No approved piles in this branch') : 'Select branch first';
        }
        pileSelect.value = '';
        updatePileInfo();
    }

    function updatePileInfo() {
        const pileSelect = document.getElementById('pile-select');
        const infoBox = document.getElementById('pile-info');
        const selected = pileSelect.options[pileSelect.selectedIndex];
        if (!selected || !selected.value || !selected.dataset.warehouse) {
            infoBox.classList.add('hidden');
            return;
        }
        document.getElementById('info-warehouse').textContent = selected.dataset.warehouse || '—';
        document.getElementById('info-volume').textContent = selected.dataset.volume || '—';
        document.getElementById('info-variety').textContent = selected.dataset.variety || '—';
        document.getElementById('info-quality').textContent = selected.dataset.quality || '—';
        infoBox.classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', filterPiles);
</script>
@endsection
