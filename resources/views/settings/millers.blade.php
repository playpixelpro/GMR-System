@extends('layouts.app')

@section('title', 'Miller Management')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Miller Management</h1>
        <p class="mt-1 text-sm text-base-content/70">Maintain the master list of rice mills used by the AMR data entry and rice milling assignment forms. Each profile records the miller category and its milling capacity for a 12-hour operation.</p>
    </div>

    @if (session('status'))
        <x-alert-box type="success" :message="session('status')" dismissible class="mb-4" />
    @endif
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

    <form method="POST" action="{{ route('millers.store') }}" class="grid gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4 md:grid-cols-4 items-end">
        @csrf
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Miller / Rice Mill Name</label>
            <input class="input w-full" name="name" value="{{ old('name') }}" placeholder="e.g. North Cotabato Rice Mill" maxlength="191" required>
            @error('name') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Category</label>
            <select class="select w-full" name="category" required>
                <option value="">Select category</option>
                @foreach (\App\Models\Miller::CATEGORY_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Capacity (bags per 12-hour operation)</label>
            <input class="input w-full" type="number" name="capacity_12h_bags" value="{{ old('capacity_12h_bags') }}" min="0" step="any" inputmode="decimal" placeholder="e.g. 1200" required>
            @error('capacity_12h_bags') <span class="text-xs text-error">{{ $message }}</span> @enderror
        </div>
        <div>
            <button class="btn btn-primary w-full inline-flex items-center justify-center gap-1.5" type="submit">
                <span class="icon-[tabler--plus] size-4"></span>
                <span>Add Miller</span>
            </button>
        </div>
    </form>

    <form method="GET" action="{{ route('settings.millers') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-base-content/10 bg-base-100 p-4">
        <div class="space-y-1 grow min-w-56">
            <label class="text-xs font-semibold text-base-content/70">Search</label>
            <input class="input w-full" type="search" name="q" value="{{ $q }}" placeholder="Search by miller name">
        </div>
        <div class="space-y-1">
            <label class="text-xs font-semibold text-base-content/70">Category</label>
            <select class="select w-full" name="category">
                <option value="">All categories</option>
                @foreach (\App\Models\Miller::CATEGORY_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-outline" type="submit">
                <span class="icon-[tabler--search] size-4"></span>
                <span>Filter</span>
            </button>
            @if ($q !== '' || $category !== '')
                <a href="{{ route('settings.millers') }}" class="btn btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-base-content/10 bg-base-100">
        @if ($millers->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 px-6 py-16 text-center">
                <span class="icon-[tabler--building-factory-2] size-10 text-base-content/40"></span>
                <p class="text-base-content/70">
                    @if ($q !== '' || $category !== '')
                        No millers match the current filters.
                    @else
                        No miller profiles yet. Add the first one above.
                    @endif
                </p>
            </div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Miller / Rice Mill</th>
                        <th>Category</th>
                        <th>Capacity (bags / 12h)</th>
                        <th>Milling Assignments</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($millers as $miller)
                        <tr>
                            <td class="font-medium">{{ $miller->name }}</td>
                            <td>
                                @if ($miller->category === 'nfa_owned')
                                    <span class="badge badge-soft badge-primary text-xs">NFA Owned</span>
                                @elseif ($miller->category === 'private')
                                    <span class="badge badge-soft badge-secondary text-xs">Private</span>
                                @else
                                    <span class="text-base-content/50">—</span>
                                @endif
                            </td>
                            <td class="font-mono text-sm">
                                @if ($miller->capacity_12h_bags !== null)
                                    {{ number_format((float) $miller->capacity_12h_bags, 0) }}
                                @else
                                    <span class="text-base-content/50">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($miller->millings_count > 0)
                                    <span class="badge badge-soft badge-info text-xs">{{ $miller->millings_count }}</span>
                                @else
                                    <span class="text-base-content/50">0</span>
                                @endif
                            </td>
                            <td class="text-sm text-base-content/70">{{ $miller->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('settings.millers.edit', $miller) }}" class="btn btn-xs btn-outline btn-primary inline-flex items-center gap-1" title="Edit this miller profile">
                                        <span class="icon-[tabler--pencil] size-3.5"></span>
                                        <span>Edit</span>
                                    </a>
                                    <form method="POST" action="{{ route('settings.millers.destroy', $miller) }}"
                                          onsubmit="return confirm('Delete miller \x27{{ addslashes($miller->name) }}\x27? Historic records keep their miller name, and any milling assignment will be unlinked. This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-outline btn-error inline-flex items-center gap-1" type="submit" title="Delete this miller profile">
                                            <span class="icon-[tabler--trash] size-3.5"></span>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
