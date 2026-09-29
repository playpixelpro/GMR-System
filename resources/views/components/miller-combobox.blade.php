@props(['name', 'value' => null, 'placeholder' => 'Type to search or add a miller'])

@php
    if (! $attributes->get('class')) {
        $attributes = $attributes->merge([
            'class' => 'block min-h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500! focus:ring-green-500!',
        ]);
    }
    $resolvedValue = $value ?? old($name);
@endphp

<div class="relative w-full" data-miller-combobox
     data-index-url="{{ route('millers.index') }}"
     data-store-url="{{ route('millers.store') }}">
    <input type="text"
           name="{{ $name }}"
           value="{{ $resolvedValue }}"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           role="combobox"
           aria-haspopup="listbox"
           aria-expanded="false"
           aria-autocomplete="list"
           {{ $attributes }}>
    <div data-miller-panel
         class="absolute left-0 right-0 top-full z-40 mt-1 hidden max-h-56 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg">
        <ul data-miller-options role="listbox" class="divide-y divide-gray-100 py-1"></ul>
        <button type="button" data-miller-add hidden
                class="block w-full border-t border-gray-100 px-3 py-2 text-left text-sm font-medium text-blue-700 hover:bg-blue-50">
        </button>
    </div>
</div>

@pushOnce('modals')
    <dialog id="miller-profile-dialog"
            class="fixed left-1/2 top-1/2 m-0 max-h-[90vh] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-gray-900/50">
        <div class="bg-white p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Add Miller Profile</h2>
                    <p class="mt-1 text-sm text-gray-500">Register the rice mill so its details can be reused on every form.</p>
                </div>
                <button type="button" data-miller-dialog-close class="text-2xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close">&times;</button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="miller-profile-name" class="block text-sm font-medium text-gray-700">Miller / Rice Mill Name</label>
                    <input type="text" id="miller-profile-name" data-miller-name required
                           class="mt-1 block w-full rounded-md border border-blue-500! bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500! focus:ring-green-500!"
                           placeholder="e.g. North Cotabato Rice Mill">
                </div>
                <div>
                    <label for="miller-profile-category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="miller-profile-category" data-miller-category required
                            class="mt-1 block w-full rounded-md border border-blue-500! bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500! focus:ring-green-500!">
                        <option value="">Select category</option>
                        <option value="nfa_owned">NFA Owned</option>
                        <option value="private">Private</option>
                    </select>
                </div>
                <div>
                    <label for="miller-profile-capacity" class="block text-sm font-medium text-gray-700">Milling Capacity (bags per 12-hour operation)</label>
                    <input type="number" id="miller-profile-capacity" data-miller-capacity required
                           min="0" step="any" inputmode="decimal"
                           class="mt-1 block w-full rounded-md border border-blue-500! bg-white px-3 py-2 text-sm shadow-sm focus:border-green-500! focus:ring-green-500!"
                           placeholder="e.g. 1200">
                </div>
                <p data-miller-dialog-error class="hidden text-sm text-red-600"></p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-miller-dialog-close
                        class="rounded-md border border-blue-500! bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" data-miller-dialog-save
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                    Add Miller
                </button>
            </div>
        </div>
    </dialog>
@endpushOnce
