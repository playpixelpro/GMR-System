@extends('layouts.app')

@section('title', 'Rice Trial Data Entry')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900">Rice Trial Data Entry</h1>
        <p class="mt-1 text-sm text-gray-500">Choose AMR or PMR and enter the trial details.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
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
                    <input type="text" name="variety" id="variety" value="{{ old('variety') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="purity" class="block text-sm font-medium text-gray-700">Purity (%)</label>
                    <input type="number" name="purity" id="purity" value="{{ old('purity') }}" min="0" max="100" step="any" required
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="aged" class="block text-sm font-medium text-gray-700">Aged (in months)</label>
                    <input type="number" name="aged" id="aged" value="{{ old('aged') }}" min="0" step="1" required
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="mc" class="block text-sm font-medium text-gray-700">MC (%)</label>
                    <input type="number" name="mc" id="mc" value="{{ old('mc') }}" min="0" max="100" step="any" required
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="quality" class="block text-sm font-medium text-gray-700">Quality (Condition)</label>
                    <select name="quality" id="quality" required
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select condition</option>
                        <option value="gqa" @selected(old('quality') === 'gqa')>GQA</option>
                        <option value="premium" @selected(old('quality') === 'premium')>Premium</option>
                        <option value="good" @selected(old('quality') === 'good')>Good</option>
                        <option value="fair" @selected(old('quality') === 'fair')>Fair</option>
                        <option value="poor" @selected(old('quality') === 'poor')>Poor</option>
                    </select>
                </div>
                <div>
                    <label for="volume" class="block text-sm font-medium text-gray-700">Volume of Pile (kg)</label>
                    <input type="text" name="volume" id="volume" value="{{ old('volume') }}" inputmode="decimal" autocomplete="off" required
                           class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
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
                <div data-trial-row class="grid grid-cols-1 gap-3 rounded-md border border-blue-200 bg-white p-3 sm:grid-cols-4">
                    <div><label class="block text-sm font-medium text-gray-700">Rice Miller</label><input type="text" name="trials[0][rice_millers]" data-amr-required class="mt-1 block min-h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Trial</label><input type="hidden" name="trials[0][trial_number]" data-trial-value><span data-trial-label class="mt-1 block rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700"></span></div>
                    <div><label class="block text-sm font-medium text-gray-700">Palay Input (kg)</label><input type="number" name="trials[0][palay_input]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Rice Output (kg)</label><input type="number" name="trials[0][rice_recovery]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div data-row-action class="flex items-end"></div>
                </div>
            </div>
            <button type="button" data-add-trial class="mt-3 rounded-md border border-blue-300 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">Add AMR Trial</button>
            <p class="mt-3 text-xs text-gray-600" data-trial-help></p>
        </div>

        <div data-test-section="pmr" class="hidden rounded-lg border border-purple-100 bg-purple-50 p-6">
            <h2 class="mb-4 text-base font-semibold text-gray-900">Laboratory Test Milling Details</h2>
            <div data-trial-rows class="space-y-3">
                <div data-trial-row class="grid grid-cols-1 gap-3 rounded-md border border-purple-200 bg-white p-3 sm:grid-cols-3">
                    <div><label class="block text-sm font-medium text-gray-700">Trial</label><input type="hidden" name="trials[0][trial_number]" data-trial-value><span data-trial-label class="mt-1 block rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700"></span></div>
                    <div><label class="block text-sm font-medium text-gray-700">Palay Input (kg)</label><input type="number" name="trials[0][palay_input]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div><label class="block text-sm font-medium text-gray-700">Rice Output (kg)</label><input type="number" name="trials[0][rice_recovery]" data-test-field min="0" step="any" required class="mt-1 block w-full rounded-md border-gray-300 px-3 py-2 text-sm"></div>
                    <div data-row-action class="flex items-end"></div>
                </div>
            </div>
            <button type="button" data-add-trial class="mt-3 rounded-md border border-purple-300 px-3 py-2 text-sm font-medium text-purple-700 hover:bg-purple-100">Add Laboratory Trial</button>
            <p class="mt-3 text-xs text-gray-600">PMR allows five laboratory trials.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700">
                Save Trial
            </button>
            <button type="reset" class="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                Clear
            </button>
            <a href="{{ route('amr.index') }}" class="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                AMR Report
            </a>
            <a href="{{ route('pmr.index') }}" class="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                PMR Report
            </a>
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
        const editRoutes = {
            amr: @json(route('records.edit', ['formType' => 'amr', 'record' => 'RECORD_ID'])),
            pmr: @json(route('records.edit', ['formType' => 'pmr', 'record' => 'RECORD_ID'])),
        };
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

        volumeInput.addEventListener('input', () => {
            volumeInput.value = formatVolume(volumeInput.value);
        });
        volumeInput.value = formatVolume(volumeInput.value);

        function updateTrialOptions() {
            const isAmr = formType.value === 'amr';
            const maximumTrial = isAmr ? 3 : 5;
            const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
            const usedTrials = selectedPile?.[formType.value]?.trials?.map(Number) || [];
            const activeSection = document.querySelector(`[data-test-section="${formType.value}"]`);
            const activeRows = activeSection.querySelectorAll('[data-trial-row]');
            const activeTrialValues = activeSection.querySelectorAll('[data-trial-value]');

            testSections.forEach((section) => {
                section.classList.toggle('hidden', section.dataset.testSection !== formType.value);
            });

            testSections.forEach((section) => {
                const isActive = section.dataset.testSection === formType.value;
                section.querySelectorAll('[data-test-field]').forEach((field) => {
                    const isExisting = field.closest('[data-trial-row]')?.dataset.existing === 'true';
                    field.disabled = !isActive || isExisting;
                    field.required = isActive && !isExisting;
                });
                section.querySelectorAll('[data-amr-required]').forEach((field) => {
                    field.required = isActive && isAmr;
                    const isExisting = field.closest('[data-trial-row]')?.dataset.existing === 'true';
                    field.disabled = !isActive || !isAmr || isExisting;
                });
            });

            const availableTrials = Array.from({ length: maximumTrial }, (_, index) => index + 1)
                .filter((trial) => !usedTrials.includes(trial));

            activeTrialValues.forEach((field, rowIndex) => {
                const trialNumber = availableTrials[rowIndex] || availableTrials[0] || 1;
                field.value = trialNumber;
                field.closest('[data-trial-row]').querySelector('[data-trial-label]').textContent = `Trial ${trialNumber}`;
            });

            trialHelp.textContent = usedTrials.length > 0
                ? `${usedTrials.length} trial(s) already completed. Add up to ${maximumTrial} total.`
                : `${formType.value.toUpperCase()} allows ${maximumTrial} trials.`;
            activeSection.querySelector('[data-add-trial]').disabled = activeRows.length >= maximumTrial || usedTrials.length >= maximumTrial;
        }

        function addTrialRow(section, update = true) {
            const rows = section.querySelector('[data-trial-rows]');
            const rowIndex = rows.querySelectorAll('[data-trial-row]').length;
            const clone = rows.querySelector('[data-trial-row]').cloneNode(true);
            clone.innerHTML = clone.innerHTML.replaceAll('[0]', `[${rowIndex}]`);
            clone.dataset.existing = 'false';
            clone.querySelectorAll('input').forEach((input) => {
                input.value = '';
                input.disabled = false;
            });
            clone.querySelector('[data-row-action]').innerHTML = '';
            rows.appendChild(clone);
            if (update) updateTrialOptions();
        }

        function resetTrialRows(section) {
            const rows = section.querySelector('[data-trial-rows]');
            while (rows.querySelectorAll('[data-trial-row]').length > 1) {
                rows.lastElementChild.remove();
            }
            const firstRow = rows.querySelector('[data-trial-row]');
            firstRow.dataset.existing = 'false';
            firstRow.querySelectorAll('input').forEach((input) => {
                input.value = '';
                input.disabled = false;
            });
            firstRow.querySelector('[data-row-action]').innerHTML = '';
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
                row.querySelector('[name$="[rice_millers]"]')?.setAttribute('value', record.rice_millers || '');
                const riceMiller = row.querySelector('[name$="[rice_millers]"]');
                if (riceMiller) riceMiller.value = record.rice_millers || '';
                row.querySelector('[name$="[palay_input]"]').value = record.palay_input || '';
                row.querySelector('[name$="[rice_recovery]"]').value = record.rice_recovery || '';
                row.querySelector('[data-row-action]').innerHTML = `<a href="${editRoutes[formType.value].replace('RECORD_ID', record.id)}" class="w-full rounded-md border border-blue-300 px-3 py-2 text-center text-xs font-medium text-blue-700 hover:bg-blue-50">Edit</a>`;
            });
        }

        function fillPileDetails() {
            const selectedPile = pileData.find((pile) => String(pile.id) === pileSelect.value);
            const details = selectedPile?.[formType.value];

            if (!details) {
                resetTrialRows(document.querySelector(`[data-test-section="${formType.value}"]`));
                updateTrialOptions();
                return;
            }

            const activeSection = document.querySelector(`[data-test-section="${formType.value}"]`);
            populateExistingTrialRows(activeSection, details.records || []);
            document.querySelector('#variety').value = details.variety || '';
            document.querySelector('#purity').value = details.purity || '';
            document.querySelector('#aged').value = details.aged || '';
            document.querySelector('#mc').value = details.mc || '';
            document.querySelector('#quality').value = details.quality || '';
            document.querySelector('#volume').value = details.volume || '';
            document.querySelector('#rice_millers').value = details.rice_millers || '';
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

        document.querySelectorAll('[data-add-trial]').forEach((button) => {
            button.addEventListener('click', () => {
                addTrialRow(button.closest('[data-test-section]'));
            });
        });

        formType.addEventListener('change', updateTrialOptions);
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
        updateTrialOptions();
        toggleNewBranch();
    </script>
@endsection
