@extends('layouts.app')

@section('title', 'Edit Miller')

@section('content')
<div class="space-y-6 max-w-2xl">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Edit Miller Profile</h1>
            <p class="mt-1 text-sm text-base-content/70">Update the name, category, or 12-hour milling capacity used by the combobox dropdowns.</p>
        </div>
        <a href="{{ route('settings.millers') }}" class="btn btn-outline btn-sm sm:btn-md gap-2">
            <span class="icon-[tabler--arrow-left] size-5"></span>
            Back
        </a>
    </div>

    @if (isset($errors) && $errors->any())
        <x-alert-box type="error" dismissible class="mb-4">
            @if ($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <ul class="list-disc pl-4 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </x-alert-box>
    @endif

    <form method="POST" action="{{ route('settings.millers.update', $miller) }}" class="grid gap-4 rounded-lg border border-base-content/10 bg-base-100 p-5">
        @csrf
        @method('PATCH')

        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Miller / Rice Mill Name</label>
            <input class="input w-full" type="text" name="name" value="{{ old('name', $miller->name) }}" maxlength="191" required>
            @error('name') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>

        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Category</label>
            <select class="select w-full" name="category" required>
                <option value="">Select category</option>
                @foreach (\App\Models\Miller::CATEGORY_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $miller->category) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>

        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Capacity (bags per 12-hour operation)</label>
            <input class="input w-full" type="number" name="capacity_12h_bags" value="{{ old('capacity_12h_bags', $miller->capacity_12h_bags) }}" min="0" step="any" inputmode="decimal" required>
            @error('capacity_12h_bags') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>

        <div class="text-xs text-base-content/60">
            Used in {{ $miller->millings()->count() }} rice milling assignment(s). Changing the name here does not rewrite miller names already saved on historical AMR/PMR records.
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('settings.millers') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary gap-2">
                <span class="icon-[tabler--device-floppy] size-4"></span>
                <span>Save Changes</span>
            </button>
        </div>
    </form>
</div>
@endsection
