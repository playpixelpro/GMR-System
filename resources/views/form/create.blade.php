@extends('layouts.app')

@section('title', 'Rice Trial Data Entry')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900">Rice Trial Data Entry</h1>
        <p class="mt-1 text-sm text-gray-500">Choose AMR or PMR and enter the trial details.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-medium">Please correct the following:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('records.store') }}" class="space-y-6" data-entry-form>
        @csrf

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="max-w-sm">
                <label for="form_type" class="block text-sm font-medium text-gray-700">Form Type</label>
                <select name="form_type" id="form_type" required
                        class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="" @selected(empty(old('form_type', $formType)))>Select Form Type</option>
                    <option value="amr" @selected(old('form_type', $formType) === 'amr')>AMR</option>
                    <option value="pmr" @selected(old('form_type', $formType) === 'pmr')>PMR</option>
                </select>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Pile Details</h2>
            <p class="mb-5 text-sm text-gray-500">A warehouse is scoped to its branch, and a pile is scoped to its warehouse.</p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700">Branch</label>
                    <select name="branch_id" id="branch_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                        <option value="__new__" @selected(old('new_branch_name'))>Add new branch...</option>
                    </select>
                    <input type="text" name="new_branch_name" id="new_branch_name" value="{{ old('new_branch_name') }}"
                           placeholder="Enter new branch name"
                           class="{{ old('new_branch_name') ? '' : 'hidden' }} mt-2 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="warehouse_id" class="block text-sm font-medium text-gray-700">Warehouse</label>
                    <select name="warehouse_id" id="warehouse_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" data-branch-id="{{ $warehouse->branch_id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                        <option value="__new__" @selected(old('new_warehouse_name'))>Add new warehouse...</option>
                    </select>
                    <input type="hidden" name="new_warehouse_name" id="new_warehouse_name" value="{{ old('new_warehouse_name') }}">
                </div>
                <div>
                    <label for="pile_id" class="block text-sm font-medium text-gray-700">Pile Number</label>
                    <select name="pile_id" id="pile_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select pile</option>
                        @foreach ($piles as $pile)
                            <option value="{{ $pile['id'] }}" data-warehouse-id="{{ $pile['warehouse_id'] }}" @selected((string) old('pile_id') === (string) $pile['id'])>{{ $pile['number'] }}</option>
                        @endforeach
                        <option value="__new__" @selected(old('new_pile_number'))>Add new pile...</option>
                    </select>
                    <input type="hidden" name="new_pile_number" id="new_pile_number" value="{{ old('new_pile_number') }}">
                </div>

                <div>
                    <label for="variety" class="block text-sm font-medium text-gray-700">Variety</label>
                    <input type="text" name="variety" id="variety" value="{{ old('variety') }}" required data-pile-detail-field
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="purity" class="block text-sm font-medium text-gray-700">Purity (%)</label>
                    <input type="number" name="purity" id="purity" value="{{ old('purity') }}" min="0" max="100" step="any" required data-pile-detail-field
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="aged" class="block text-sm font-medium text-gray-700">Aged (in months)</label>
                    <input type="number" name="aged" id="aged" value="{{ old('aged') }}" min="0" step="1" required data-pile-detail-field
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="mc" class="block text-sm font-medium text-gray-700">MC (%)</label>
                    <input type="number" name="mc" id="mc" value="{{ old('mc') }}" min="0" max="100" step="any" required data-pile-detail-field
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="quality" class="block text-sm font-medium text-gray-700">Quality (Condition)</label>
                    <select name="quality" id="quality" required data-pile-detail-field
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select condition</option>
                        <option value="good" @selected(strtolower((string) old('quality')) === 'good')>Good</option>
                        <option value="treated fair" @selected(in_array(strtolower((string) old('quality')), ['treated fair', 'treated_fair'], true))>Treated Fair</option>
                        <option value="poor" @selected(strtolower((string) old('quality')) === 'poor')>Poor</option>
                    </select>
                </div>
                <div>
                    <label for="volume" class="block text-sm font-medium text-gray-700">Volume of Pile (kg)</label>
                    <input type="text" name="volume" id="volume" value="{{ old('volume') }}" inputmode="decimal" autocomplete="off" required data-pile-detail-field
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" data-edit-pile-details disabled class="min-w-48 rounded-md border border-blue-300 bg-white px-8 py-4 text-lg font-semibold text-blue-700 shadow-sm enabled:hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50">
                    Edit
                </button>
            </div>
        </div>

        <dialog id="warehouse-dialog" class="fixed left-1/2 top-1/2 m-0 max-h-[90vh] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-gray-900/50">
            <div class="bg-white p-6" data-warehouse-dialog-form>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Add Warehouse</h2>
                        <p class="mt-1 text-sm text-gray-500">Choose the branch that owns this warehouse.</p>
                    </div>
                    <button type="button" data-close-warehouse-dialog class="text-2xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close">&times;</button>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <label for="new_warehouse_branch_id" class="block text-sm font-medium text-gray-700">Branch</label>
                        <select id="new_warehouse_branch_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="new_warehouse_modal_name" class="block text-sm font-medium text-gray-700">Warehouse Name</label>
                        <input type="text" id="new_warehouse_modal_name" required
                               class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <p class="hidden text-sm text-red-600" data-warehouse-dialog-error></p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-close-warehouse-dialog class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" data-save-warehouse-dialog class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Warehouse</button>
                </div>
            </div>
        </dialog>

        <dialog id="pile-dialog" class="fixed left-1/2 top-1/2 m-0 max-h-[90vh] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-gray-900/50">
            <div class="bg-white p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Add Pile</h2>
                        <p class="mt-1 text-sm text-gray-500">Choose the branch and warehouse for this pile.</p>
                    </div>
                    <button type="button" data-close-pile-dialog class="text-2xl leading-none text-gray-400 hover:text-gray-700" aria-label="Close">&times;</button>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <label for="new_pile_branch_id" class="block text-sm font-medium text-gray-700">Branch</label>
                        <select id="new_pile_branch_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="">Select branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="new_pile_warehouse_id" class="block text-sm font-medium text-gray-700">Warehouse</label>
                        <select id="new_pile_warehouse_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm"></select>
                    </div>
                    <div>
                        <label for="new_pile_modal_number" class="block text-sm font-medium text-gray-700">Pile Number</label>
                        <input type="text" id="new_pile_modal_number" required
                               class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm">
                    </div>
                    <p class="hidden text-sm text-red-600" data-pile-dialog-error></p>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-close-pile-dialog class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" data-save-pile-dialog class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Pile</button>
                </div>
            </div>
        </dialog>

        <div data-test-section="amr" class="rounded-lg border border-blue-100 bg-blue-50 p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Test Milling Details</h2>
            <div data-trial-rows class="space-y-3">
                <div data-trial-row class="grid grid-cols-1 gap-3 rounded-md border border-blue-200 bg-white p-3 sm:grid-cols-6">
                    <div><label class="block text-sm font-medium text-gray-700">Test Milling Date</label><input type="text" name="trials[0][test_milling_date]" data-test-field data-flatpickr-date required class="input max-w-sm mt-1 block min-h-10 w-full" placeholder="Month DD, YYYY"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Rice Miller</label><input type="text" name="trials[0][rice_millers]" data-amr-required class="mt-1 block min-h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Trial</label><input type="hidden" name="trials[0][trial_number]" data-trial-value><span data-trial-label class="mt-1 block rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700"></span></div>
                    <div><label class="block text-sm font-medium text-gray-700">Palay Input (kg)</label><input type="number" name="trials[0][palay_input]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Rice Output (kg)</label><input type="number" name="trials[0][rice_recovery]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div data-row-action class="flex items-end gap-2"><button type="button" disabled class="w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-400 disabled:cursor-not-allowed">Edit</button><button type="button" data-delete-trial aria-label="Delete trial" title="Delete trial" class="rounded-md border border-red-200 p-2 text-red-600 hover:bg-red-50"><span class="icon-[tabler--trash] h-5 w-5" aria-hidden="true"></span></button></div>
                </div>
            </div>
            <button type="button" data-add-trial class="mt-3 rounded-md border border-blue-300 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">Add AMR Trial</button>
            <p class="mt-3 text-xs text-gray-600" data-trial-help></p>
        </div>

        <div data-test-section="pmr" class="hidden rounded-lg border border-red-100 bg-red-50 p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Laboratory Test Milling Details</h2>
            <div data-trial-rows class="space-y-3">
                <div data-trial-row class="grid grid-cols-1 gap-3 rounded-md border border-red-200 bg-white p-3 sm:grid-cols-5">
                    <div><label class="block text-sm font-medium text-gray-700">Test Milling Date</label><input type="text" name="trials[0][test_milling_date]" data-test-field data-flatpickr-date required class="input max-w-sm mt-1 block min-h-10 w-full" placeholder="Month DD, YYYY"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Trial</label><input type="hidden" name="trials[0][trial_number]" data-trial-value><span data-trial-label class="mt-1 block rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700"></span></div>
                    <div><label class="block text-sm font-medium text-gray-700">Palay Input (kg)</label><input type="number" name="trials[0][palay_input]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Rice Output (kg)</label><input type="number" name="trials[0][rice_recovery]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div data-row-action class="flex items-end gap-2"><button type="button" disabled class="w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-400 disabled:cursor-not-allowed">Edit</button><button type="button" data-delete-trial aria-label="Delete trial" title="Delete trial" class="rounded-md border border-red-200 p-2 text-red-600 hover:bg-red-50"><span class="icon-[tabler--trash] h-5 w-5" aria-hidden="true"></span></button></div>
                </div>
            </div>
            <button type="button" data-add-trial class="mt-3 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">Add Laboratory Trial</button>
            <p class="mt-3 text-xs text-gray-600" data-trial-help></p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="reset" class="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                Cancel
            </button>
            <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                Save Trial
            </button>
        </div>
    </form>

    <script>
        const formType = document.querySelector('#form_type');
        const testSections = document.querySelectorAll('[data-test-section]');
        const trialValues = document.querySelectorAll('[data-trial-value]');
        const testFields = document.querySelectorAll('[data-test-field]');
        const trialHelp = document.querySelector('[data-trial-help]');
        const amrRequiredFields = document.querySelectorAll('[data-amr-required]');
        const oldTrial = @json(old('no_of_trial'));
        const volumeInput = document.querySelector('#volume');
        const branchSelect = document.querySelector('#branch_id');
        const newBranchInput = document.querySelector('#new_branch_name');
        const warehouseSelect = document.querySelector('#warehouse_id');
        const newWarehouseInput = document.querySelector('#new_warehouse_name');
        const pileSelect = document.querySelector('#pile_id');
        const newPileInput = document.querySelector('#new_pile_number');
        const pileDialog = document.querySelector('#pile-dialog');
        const newPileBranchSelect = document.querySelector('#new_pile_branch_id');
        const newPileWarehouseSelect = document.querySelector('#new_pile_warehouse_id');
        const newPileModalNumber = document.querySelector('#new_pile_modal_number');
        const pileDialogError = document.querySelector('[data-pile-dialog-error]');
        const pileOptions = Array.from(pileSelect.querySelectorAll('option[data-warehouse-id]'));
        const pileData = @json($piles);
        const pileDetailFields = document.querySelectorAll('[data-pile-detail-field]');
        const editPileDetailsButton = document.querySelector('[data-edit-pile-details]');
        let pileDetailsLocked = false;
        let pileHasSavedDetails = false;
        const updatePileDetailsRoute = @json(route('piles.details.update', ['pile' => 'PILE_ID']));
        const updateRoute = @json(route('records.update', ['formType' => 'FORM_TYPE', 'record' => 'RECORD_ID']));
        const destroyRoute = @json(route('records.destroy', ['formType' => 'FORM_TYPE', 'record' => 'RECORD_ID']));
        const warehouseDialog = document.querySelector('#warehouse-dialog');
        const warehouseDialogForm = document.querySelector('[data-warehouse-dialog-form]');
        const newWarehouseBranchSelect = document.querySelector('#new_warehouse_branch_id');
        const newWarehouseModalName = document.querySelector('#new_warehouse_modal_name');
        let warehouseOptions = Array.from(warehouseSelect.querySelectorAll('option[data-branch-id]'));
        const warehouseDialogError = document.querySelector('[data-warehouse-dialog-error]');

        function formatVolume(value) {
            const normalized = value.replace(/[^0-9.]/g, '');
            const parts = normalized.split('.');
            const integerPart = parts.shift() || '';
            const decimalPart = parts.join('').slice(0, 3);
            const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return decimalPart ? `${formattedInteger}.${decimalPart}` : formattedInteger;
        }

        function trialActionMarkup(isSaved = false) {
            const editButton = isSaved
                ? '<button type="button" data-edit-trial class="w-full rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">Edit</button>'
                : '<button type="button" disabled class="w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm font-medium text-gray-400 disabled:cursor-not-allowed">Edit</button>';

            return `${editButton}<button type="button" data-delete-trial aria-label="Delete trial" title="Delete trial" class="rounded-md border border-red-200 p-2 text-red-600 hover:bg-red-50"><span class="icon-[tabler--trash] h-5 w-5" aria-hidden="true"></span></button>`;
        }

        volumeInput.addEventListener('input', () => {
            volumeInput.value = formatVolume(volumeInput.value);
        });
        volumeInput.value = formatVolume(volumeInput.value);

        function reindexTrialRows(section) {
            section.querySelectorAll('[data-trial-row]').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/trials\[\d+\]/, `trials[${index}]`);
                });
            });
        }

        function initFlatpickr(input) {
            if (!input || typeof flatpickr === 'undefined') {
                return;
            }
            if (input._flatpickr) {
                input._flatpickr.destroy();
            }
            if (input.parentElement) {
                input.parentElement.querySelectorAll('input:not([name])').forEach((alt) => alt.remove());
            }
            flatpickr(input, {
                altInput: true,
                altFormat: 'F j, Y',
                dateFormat: 'Y-m-d',
                altInputClass: 'input max-w-sm mt-1 block min-h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm',
                allowInput: true,
            });
        }

        function updateTrialOptions() {
            const isAmr = formType.value === 'amr';
            const maximumTrial = isAmr ? 3 : 5;
            const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
            const usedTrials = formType.value ? (selectedPile?.[formType.value]?.trials?.map(Number) || []) : [];
            const activeSection = formType.value ? document.querySelector(`[data-test-section="${formType.value}"]`) : null;
            const activeRows = activeSection ? activeSection.querySelectorAll('[data-trial-row]') : [];
            const activeTrialValues = activeSection ? activeSection.querySelectorAll('[data-trial-value]') : [];

            testSections.forEach((section) => {
                section.classList.toggle('hidden', !formType.value || section.dataset.testSection !== formType.value);
            });

            testSections.forEach((section) => {
                const isActive = Boolean(formType.value && section.dataset.testSection === formType.value);
                section.querySelectorAll('[data-test-field]').forEach((field) => {
                    const row = field.closest('[data-trial-row]');
                    const isExisting = row?.dataset.existing === 'true';
                    const shouldDisable = !isActive || (isExisting && row.dataset.editing !== 'true');
                    field.disabled = shouldDisable;
                    field.required = isActive && !isExisting;
                    if (field._flatpickr?.altInput) {
                        field._flatpickr.altInput.disabled = shouldDisable;
                    }
                });
                section.querySelectorAll('[data-amr-required]').forEach((field) => {
                    field.required = isActive && isAmr;
                    const row = field.closest('[data-trial-row]');
                    const isExisting = row?.dataset.existing === 'true';
                    field.disabled = !isActive || !isAmr || (isExisting && row.dataset.editing !== 'true');
                });
            });

            if (!activeSection) {
                if (trialHelp) {
                    trialHelp.textContent = 'Please select a form type (AMR or PMR) to enter trials.';
                }
                return;
            }

            const availableTrials = Array.from({ length: maximumTrial }, (_, index) => index + 1)
                .filter((trial) => !usedTrials.includes(trial));

            activeTrialValues.forEach((field, rowIndex) => {
                const row = field.closest('[data-trial-row]');
                if (row.dataset.existing === 'true') {
                    return;
                }
                const trialNumber = availableTrials[rowIndex] || availableTrials[0] || 1;
                field.value = trialNumber;
                row.querySelector('[data-trial-label]').textContent = `Trial ${trialNumber}`;
            });

            const trialHelp = activeSection ? activeSection.querySelector('[data-trial-help]') : document.querySelector('[data-trial-help]');
            if (trialHelp) {
                trialHelp.textContent = usedTrials.length > 0
                    ? `${usedTrials.length} trial(s) already completed. Add up to ${maximumTrial} total.`
                    : `${formType.value.toUpperCase()} allows ${maximumTrial} trials.`;
            }
            if (activeSection) {
                const addTrialButton = activeSection.querySelector('[data-add-trial]');
                if (addTrialButton) {
                    addTrialButton.disabled = activeRows.length >= maximumTrial || usedTrials.length >= maximumTrial;
                }
            }
        }

        function addTrialRow(section, update = true) {
            const rows = section.querySelector('[data-trial-rows]');
            const rowIndex = rows.querySelectorAll('[data-trial-row]').length;
            const templateRow = rows.querySelector('[data-trial-row]');
            const clone = templateRow.cloneNode(true);
            clone.innerHTML = clone.innerHTML.replaceAll('[0]', `[${rowIndex}]`);

            // Clean up cloned flatpickr altInputs
            clone.querySelectorAll('input:not([name])').forEach((alt) => alt.remove());
            const dateInput = clone.querySelector('[name$="[test_milling_date]"]');
            if (dateInput) {
                dateInput.type = 'text';
                dateInput.value = '';
                dateInput.removeAttribute('value');
                dateInput.classList.remove('flatpickr-input');
                dateInput.style.display = '';
                delete dateInput._flatpickr;
                if (dateInput.parentElement) {
                    dateInput.parentElement.querySelectorAll('input:not([name])').forEach((alt) => alt.remove());
                }
            }

            clone.dataset.existing = 'false';
            clone.dataset.editing = 'false';
            delete clone.dataset.recordId;
            clone.classList.replace('bg-gray-100', 'bg-white');
            clone.querySelectorAll('input').forEach((input) => {
                input.value = '';
                input.removeAttribute('value');
                input.disabled = false;
                input.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
            });
            clone.querySelector('[data-row-action]').innerHTML = trialActionMarkup();
            rows.appendChild(clone);

            if (dateInput) {
                initFlatpickr(dateInput);
            }

            if (update) updateTrialOptions();
        }

        function resetTrialRows(section) {
            const rows = section.querySelector('[data-trial-rows]');
            rows.querySelectorAll('[data-trial-row]').forEach((row, index) => {
                if (index > 0) {
                    const dateInput = row.querySelector('[name$="[test_milling_date]"]');
                    if (dateInput && dateInput._flatpickr) {
                        dateInput._flatpickr.destroy();
                    }
                    row.remove();
                }
            });
            const firstRow = rows.querySelector('[data-trial-row]');
            firstRow.dataset.existing = 'false';
            firstRow.dataset.editing = 'false';
            delete firstRow.dataset.recordId;
            firstRow.classList.replace('bg-gray-100', 'bg-white');
            firstRow.querySelectorAll('input').forEach((input) => {
                input.value = '';
                input.removeAttribute('value');
                input.disabled = false;
                input.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
            });
            const firstDateInput = firstRow.querySelector('[name$="[test_milling_date]"]');
            if (firstDateInput) {
                if (firstDateInput._flatpickr) {
                    firstDateInput._flatpickr.clear();
                }
                if (firstDateInput.parentElement) {
                    firstDateInput.parentElement.querySelectorAll('input:not([name])').forEach((alt) => {
                        if (!firstDateInput._flatpickr || alt !== firstDateInput._flatpickr.altInput) {
                            alt.remove();
                        }
                    });
                }
                if (firstDateInput._flatpickr?.altInput) {
                    firstDateInput._flatpickr.altInput.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                    firstDateInput._flatpickr.altInput.disabled = false;
                }
            }
            firstRow.querySelector('[data-row-action]').innerHTML = trialActionMarkup();
        }

        function populateExistingTrialRows(section, records) {
            resetTrialRows(section);
            for (let index = 1; index < records.length; index += 1) {
                addTrialRow(section, false);
            }

            const rows = section.querySelectorAll('[data-trial-row]');
            records.forEach((record, index) => {
                const row = rows[index];
                row.dataset.existing = 'true';
                row.dataset.recordId = record.id;
                row.dataset.editing = 'false';
                row.classList.replace('bg-white', 'bg-gray-100');
                row.querySelectorAll('[data-test-field], [data-amr-required]').forEach((field) => {
                    field.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                });
                row.querySelector('[name$="[rice_millers]"]')?.setAttribute('value', record.rice_millers || '');
                const riceMiller = row.querySelector('[name$="[rice_millers]"]');
                if (riceMiller) riceMiller.value = record.rice_millers || '';
                row.querySelector('[name$="[trial_number]"]').value = record.trial_number;
                row.querySelector('[data-trial-label]').textContent = `Trial ${record.trial_number}`;
                const dateInput = row.querySelector('[name$="[test_milling_date]"]');
                if (dateInput) {
                    if (dateInput.parentElement) {
                        dateInput.parentElement.querySelectorAll('input:not([name])').forEach((alt) => {
                            if (!dateInput._flatpickr || alt !== dateInput._flatpickr.altInput) {
                                alt.remove();
                            }
                        });
                    }
                    if (dateInput._flatpickr) {
                        dateInput._flatpickr.setDate(record.test_milling_date || '', true);
                    } else {
                        dateInput.value = record.test_milling_date || '';
                        initFlatpickr(dateInput);
                    }
                    if (dateInput._flatpickr?.altInput) {
                        dateInput._flatpickr.altInput.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        dateInput._flatpickr.altInput.disabled = true;
                    }
                }
                row.querySelector('[name$="[palay_input]"]').value = record.palay_input || '';
                row.querySelector('[name$="[rice_recovery]"]').value = record.rice_recovery || '';
                row.querySelector('[data-row-action]').innerHTML = trialActionMarkup(true);
            });
        }

        function setPileDetailsLocked(locked, hasSavedDetails = pileHasSavedDetails) {
            pileDetailsLocked = locked;
            pileHasSavedDetails = hasSavedDetails;
            pileDetailFields.forEach((field) => {
                if (field.tagName === 'SELECT') {
                    field.disabled = locked;
                } else {
                    field.readOnly = locked;
                }
                field.classList.toggle('bg-gray-100', locked);
                field.classList.toggle('text-gray-500', locked);
                field.classList.toggle('cursor-not-allowed', locked);
            });
            editPileDetailsButton.disabled = !pileHasSavedDetails;
            editPileDetailsButton.textContent = pileHasSavedDetails && !locked ? 'Save' : 'Edit';
        }

        function fillPileDetails() {
            const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
            const hasSavedDetails = Boolean(selectedPile?.shared?.variety || selectedPile?.amr?.records?.length || selectedPile?.pmr?.records?.length);
            const details = (formType.value && selectedPile?.[formType.value]) || selectedPile?.shared || selectedPile?.amr || selectedPile?.pmr;

            setPileDetailsLocked(hasSavedDetails, hasSavedDetails);

            if (!details || !hasSavedDetails) {
                if (formType.value) {
                    resetTrialRows(document.querySelector(`[data-test-section="${formType.value}"]`));
                }
                updateTrialOptions();
                return;
            }

            if (formType.value) {
                const activeSection = document.querySelector(`[data-test-section="${formType.value}"]`);
                if (activeSection) {
                    populateExistingTrialRows(activeSection, selectedPile?.[formType.value]?.records || []);
                }
            }
            document.querySelector('#variety').value = details.variety || '';
            document.querySelector('#purity').value = details.purity || '';
            document.querySelector('#aged').value = details.aged || '';
            document.querySelector('#mc').value = details.mc || '';
            const normalizedQuality = (details.quality || '').toLowerCase().replace('_', ' ');
            document.querySelector('#quality').value = normalizedQuality;
            document.querySelector('#volume').value = details.volume ? formatVolume(String(details.volume)) : '';
            updateTrialOptions();
        }

        function populatePileWarehouseOptions() {
            const branchId = newPileBranchSelect.value;
            newPileWarehouseSelect.innerHTML = '<option value="">Select warehouse</option>';

            warehouseOptions
                .filter((option) => option.dataset.branchId === branchId)
                .forEach((option) => {
                    newPileWarehouseSelect.add(new Option(option.textContent, option.value));
                });
        }

        function openPileDialog() {
            newPileBranchSelect.value = branchSelect.value !== '__new__' ? branchSelect.value : '';
            populatePileWarehouseOptions();
            newPileWarehouseSelect.value = warehouseSelect.value !== '__new__' ? warehouseSelect.value : '';
            newPileModalNumber.value = newPileInput.value || '';
            pileDialog.showModal();
            newPileBranchSelect.focus();
        }

        function closePileDialog() {
            pileDialog.close();
            if (!newPileInput.value) {
                pileSelect.value = '';
                toggleNewPile(false);
            }
        }

        function toggleNewPile(openDialog = false) {
            const isNewPile = pileSelect.value === '__new__';
            newPileInput.required = isNewPile;
            pileSelect.disabled = isNewPile;

            if (isNewPile) {
                setPileDetailsLocked(false, false);
                resetTrialRows(document.querySelector(`[data-test-section="${formType.value}"]`));
                updateTrialOptions();
                if (openDialog) {
                    openPileDialog();
                }
                return;
            }

            newPileInput.value = '';
            fillPileDetails();
        }

        function togglePiles() {
            const warehouseId = warehouseSelect.value;
            const isNewWarehouse = warehouseId === '__new__';

            pileOptions.forEach((option) => {
                option.hidden = isNewWarehouse || option.dataset.warehouseId !== warehouseId;
            });

            if (isNewWarehouse) {
                pileSelect.value = '__new__';
            } else if (!pileOptions.some((option) => option.value === pileSelect.value && !option.hidden)) {
                pileSelect.value = '';
            }

            toggleNewPile(false);
        }

        function toggleNewBranch() {
            const isNewBranch = branchSelect.value === '__new__';
            newBranchInput.classList.toggle('hidden', !isNewBranch);
            newBranchInput.required = isNewBranch;
            branchSelect.disabled = isNewBranch;
            toggleWarehouses();
        }

        function toggleWarehouses() {
            const branchId = branchSelect.value;
            const isNewBranch = branchId === '__new__';

            warehouseOptions.forEach((option) => {
                option.hidden = isNewBranch || option.dataset.branchId !== branchId;
            });

            if (isNewBranch) {
                warehouseSelect.value = '__new__';
            } else if (!warehouseOptions.some((option) => option.value === warehouseSelect.value && !option.hidden)) {
                warehouseSelect.value = '';
            }

            toggleNewWarehouse();
            togglePiles();
        }

        function toggleNewWarehouse() {
            const isNewWarehouse = warehouseSelect.value === '__new__';
            newWarehouseInput.required = isNewWarehouse;
            warehouseSelect.disabled = branchSelect.value === '__new__' || isNewWarehouse;
        }

        function openWarehouseDialog() {
            const selectedBranch = branchSelect.value;
            newWarehouseBranchSelect.value = selectedBranch !== '__new__' ? selectedBranch : '';
            newWarehouseModalName.value = newWarehouseInput.value || '';
            warehouseDialog.showModal();
            newWarehouseBranchSelect.focus();
        }

        function closeWarehouseDialog() {
            warehouseDialog.close();
            if (!newWarehouseInput.value) {
                warehouseSelect.value = '';
                toggleNewWarehouse();
            }
        }

        document.querySelector('[data-save-warehouse-dialog]').addEventListener('click', async (event) => {
            if (!newWarehouseBranchSelect.reportValidity() || !newWarehouseModalName.reportValidity()) {
                return;
            }

            const saveButton = event.currentTarget;
            saveButton.disabled = true;
            warehouseDialogError.classList.add('hidden');

            try {
                const response = await fetch('{{ route('warehouses.store') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        branch_id: newWarehouseBranchSelect.value,
                        name: newWarehouseModalName.value.trim(),
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'The warehouse could not be saved.');
                }

                const option = new Option(data.name, data.id, true, true);
                option.dataset.branchId = data.branch_id;
                warehouseSelect.add(option);
                warehouseOptions.push(option);
                branchSelect.disabled = false;
                branchSelect.value = data.branch_id;
                newWarehouseInput.value = '';
                warehouseDialog.close();
                toggleWarehouses();
                warehouseSelect.value = String(data.id);
                toggleNewWarehouse();
            } catch (error) {
                warehouseDialogError.textContent = error.message;
                warehouseDialogError.classList.remove('hidden');
            } finally {
                saveButton.disabled = false;
            }
        });

        document.querySelectorAll('[data-close-warehouse-dialog]').forEach((button) => {
            button.addEventListener('click', closeWarehouseDialog);
        });

        newPileBranchSelect.addEventListener('change', populatePileWarehouseOptions);
        document.querySelector('[data-save-pile-dialog]').addEventListener('click', async (event) => {
            if (!newPileBranchSelect.reportValidity() || !newPileWarehouseSelect.reportValidity() || !newPileModalNumber.reportValidity()) {
                return;
            }

            const saveButton = event.currentTarget;
            saveButton.disabled = true;
            pileDialogError.classList.add('hidden');

            try {
                const response = await fetch('{{ route('piles.store') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        branch_id: newPileBranchSelect.value,
                        warehouse_id: newPileWarehouseSelect.value,
                        number: newPileModalNumber.value.trim(),
                    }),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'The pile could not be saved.');
                }

                const option = new Option(data.number, data.id, true, true);
                option.dataset.warehouseId = data.warehouse_id;
                pileSelect.add(option);
                pileOptions.push(option);
                branchSelect.disabled = false;
                branchSelect.value = data.branch_id;
                warehouseSelect.disabled = false;
                warehouseSelect.value = data.warehouse_id;
                newPileInput.value = '';
                pileDialog.close();
                toggleWarehouses();
                pileSelect.value = String(data.id);
                toggleNewPile(false);
            } catch (error) {
                pileDialogError.textContent = error.message;
                pileDialogError.classList.remove('hidden');
            } finally {
                saveButton.disabled = false;
            }
        });

        document.querySelectorAll('[data-close-pile-dialog]').forEach((button) => {
            button.addEventListener('click', closePileDialog);
        });

        testSections.forEach((section) => {
            section.addEventListener('click', async (event) => {
                const deleteButton = event.target.closest('[data-delete-trial]');
                if (deleteButton) {
                    const row = deleteButton.closest('[data-trial-row]');
                    const rows = section.querySelector('[data-trial-rows]');
                    const isSaved = row.dataset.existing === 'true';

                    if (isSaved && !window.confirm('Delete this saved trial? This cannot be undone.')) {
                        return;
                    }

                    if (isSaved) {
                        deleteButton.disabled = true;
                        try {
                            const url = destroyRoute
                                .replace('FORM_TYPE', formType.value)
                                .replace('RECORD_ID', row.dataset.recordId);
                            const response = await fetch(url, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                            });
                            const data = await response.json();
                            if (!response.ok) {
                                throw new Error(data.message || 'The trial could not be deleted.');
                            }

                            const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
                            const pileTrials = selectedPile?.[formType.value];
                            if (pileTrials) {
                                const trialNumber = Number(row.querySelector('[data-trial-value]').value);
                                pileTrials.records = pileTrials.records.filter((record) => String(record.id) !== row.dataset.recordId);
                                pileTrials.trials = pileTrials.trials.filter((trial) => Number(trial) !== trialNumber);
                            }
                        } catch (error) {
                            window.alert(error.message);
                            deleteButton.disabled = false;
                            return;
                        }
                    }

                    if (rows.querySelectorAll('[data-trial-row]').length === 1) {
                        resetTrialRows(section);
                    } else {
                        const dateInput = row.querySelector('[name$="[test_milling_date]"]');
                        if (dateInput && dateInput._flatpickr) {
                            dateInput._flatpickr.destroy();
                        }
                        row.remove();
                        reindexTrialRows(section);
                    }
                    updateTrialOptions();
                    return;
                }

                const button = event.target.closest('[data-edit-trial]');
                if (!button) {
                    return;
                }

                const row = button.closest('[data-trial-row]');
                const fields = row.querySelectorAll('[data-test-field], [data-amr-required]');

                if (row.dataset.editing !== 'true') {
                    row.dataset.editing = 'true';
                    fields.forEach((field) => {
                        field.disabled = false;
                        field.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        if (field._flatpickr?.altInput) {
                            field._flatpickr.altInput.disabled = false;
                            field._flatpickr.altInput.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        }
                    });
                    row.classList.replace('bg-gray-100', 'bg-white');
                    button.textContent = 'Save';
                    return;
                }

                button.disabled = true;
                const payload = new URLSearchParams({
                    _token: document.querySelector('meta[name="csrf-token"]').content,
                    test_milling_date: row.querySelector('[name$="[test_milling_date]"]').value,
                    palay_input: row.querySelector('[name$="[palay_input]"]').value,
                    rice_recovery: row.querySelector('[name$="[rice_recovery]"]').value,
                });
                const riceMiller = row.querySelector('[name$="[rice_millers]"]');
                if (riceMiller) {
                    payload.set('rice_millers', riceMiller.value);
                }

                try {
                    const url = updateRoute
                        .replace('FORM_TYPE', formType.value)
                        .replace('RECORD_ID', row.dataset.recordId);
                    const response = await fetch(url, {
                        method: 'PATCH',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: payload,
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The trial could not be updated.');
                    }

                    const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
                    const savedRecord = selectedPile?.[formType.value]?.records?.find((record) => String(record.id) === row.dataset.recordId);
                    if (savedRecord) {
                        savedRecord.rice_millers = riceMiller?.value || null;
                        savedRecord.palay_input = row.querySelector('[name$="[palay_input]"]').value;
                        savedRecord.rice_recovery = row.querySelector('[name$="[rice_recovery]"]').value;
                        savedRecord.test_milling_date = row.querySelector('[name$="[test_milling_date]"]').value;
                    }

                    row.dataset.editing = 'false';
                    row.classList.replace('bg-white', 'bg-gray-100');
                    fields.forEach((field) => {
                        field.disabled = true;
                        field.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        if (field._flatpickr?.altInput) {
                            field._flatpickr.altInput.disabled = true;
                            field._flatpickr.altInput.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                        }
                    });
                    button.textContent = 'Edit';
                } catch (error) {
                    window.alert(error.message);
                } finally {
                    button.disabled = false;
                }
            });
        });

        document.querySelectorAll('[data-add-trial]').forEach((button) => {
            button.addEventListener('click', () => {
                addTrialRow(button.closest('[data-test-section]'));
            });
        });

        formType.addEventListener('change', () => {
            fillPileDetails();
            updateTrialOptions();
        });
        editPileDetailsButton.addEventListener('click', async () => {
            if (pileDetailsLocked) {
                setPileDetailsLocked(false);
                return;
            }

            const selectedPileId = pileSelect.value;
            editPileDetailsButton.disabled = true;
            const payload = new URLSearchParams({
                _token: document.querySelector('meta[name="csrf-token"]').content,
                variety: document.querySelector('#variety').value,
                purity: document.querySelector('#purity').value,
                aged: document.querySelector('#aged').value,
                mc: document.querySelector('#mc').value,
                quality: document.querySelector('#quality').value,
                volume: document.querySelector('#volume').value,
            });

            try {
                const response = await fetch(updatePileDetailsRoute.replace('PILE_ID', selectedPileId), {
                    method: 'PATCH',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload,
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Pile details could not be updated.');
                }

                const selectedPile = pileData.find((pile) => String(pile.id) === selectedPileId);
                ['amr', 'pmr'].forEach((type) => {
                    const details = selectedPile?.[type];
                    if (details) {
                        details.variety = payload.get('variety');
                        details.purity = payload.get('purity');
                        details.aged = payload.get('aged');
                        details.mc = payload.get('mc');
                        details.quality = payload.get('quality');
                        details.volume = payload.get('volume').replaceAll(',', '');
                    }
                });
                setPileDetailsLocked(true, true);
            } catch (error) {
                window.alert(error.message);
                editPileDetailsButton.disabled = false;
            }
        });
        document.querySelector('[data-entry-form]').addEventListener('submit', () => {
            pileDetailFields.forEach((field) => {
                field.disabled = false;
            });
        });
        document.querySelector('[data-entry-form]').addEventListener('reset', () => {
            setTimeout(() => {
                testSections.forEach((section) => {
                    resetTrialRows(section);
                });
                updateTrialOptions();
            }, 0);
        });
        branchSelect.addEventListener('change', toggleNewBranch);
        pileSelect.addEventListener('change', () => {
            toggleNewPile(pileSelect.value === '__new__');
        });
        warehouseSelect.addEventListener('change', () => {
            if (warehouseSelect.value === '__new__') {
                openWarehouseDialog();
                return;
            }
            newWarehouseInput.value = '';
            toggleNewWarehouse();
            togglePiles();
        });
        function initAllDatePickers() {
            document.querySelectorAll('[data-flatpickr-date]').forEach(initFlatpickr);
        }
        window.addEventListener('load', initAllDatePickers);
        initAllDatePickers();
        updateTrialOptions();
        toggleNewBranch();
    </script>
@endsection
