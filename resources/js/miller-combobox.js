const CATEGORY_LABELS = {
    nfa_owned: 'NFA Owned',
    private: 'Private',
};

const capacityFormatter = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 3,
});

let millerCache = null;
let cachePromise = null;
let activeCombo = null;

function getDialog() {
    return document.getElementById('miller-profile-dialog');
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function loadMillers(combo) {
    if (millerCache) {
        return Promise.resolve(millerCache);
    }

    if (!cachePromise) {
        cachePromise = fetch(combo.dataset.indexUrl, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : []))
            .then((list) => {
                millerCache = Array.isArray(list) ? list : [];
                return millerCache;
            })
            .catch(() => {
                // Suggestions stay unavailable for this attempt; allow a retry
                // on the next focus while "Add" keeps working.
                cachePromise = null;
                return [];
            });
    }

    return cachePromise;
}

function upsertMiller(miller) {
    millerCache = millerCache ?? [];

    const lowerName = String(miller.name).toLowerCase();
    const index = millerCache.findIndex(
        (entry) => String(entry.name).toLowerCase() === lowerName,
    );

    if (index >= 0) {
        millerCache[index] = miller;
    } else {
        millerCache.push(miller);
    }

    millerCache.sort((a, b) => a.name.localeCompare(b.name));
}

function createOptionButton(miller) {
    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('role', 'option');
    button.dataset.millerOption = '';
    button.dataset.millerName = miller.name;
    button.className =
        'block w-full px-3 py-2 text-left text-sm text-gray-900 hover:bg-blue-50';

    const nameLine = document.createElement('span');
    nameLine.textContent = miller.name;
    button.append(nameLine);

    const details = [];
    if (miller.category && CATEGORY_LABELS[miller.category]) {
        details.push(CATEGORY_LABELS[miller.category]);
    }
    if (
        miller.capacity_12h_bags !== null &&
        miller.capacity_12h_bags !== undefined &&
        miller.capacity_12h_bags !== '' &&
        !Number.isNaN(parseFloat(miller.capacity_12h_bags))
    ) {
        details.push(
            `${capacityFormatter.format(parseFloat(miller.capacity_12h_bags))} bags / 12h`,
        );
    }

    if (details.length > 0) {
        const detailsLine = document.createElement('span');
        detailsLine.className = 'block text-xs text-gray-500';
        detailsLine.textContent = details.join(' · ');
        button.append(detailsLine);
    }

    return button;
}

function closePanel(combo) {
    const input = combo.querySelector('input[name]');
    const panel = combo.querySelector('[data-miller-panel]');

    panel.classList.add('hidden');
    if (input) {
        input.setAttribute('aria-expanded', 'false');
    }
    combo.querySelectorAll('[data-miller-option]').forEach((button) => {
        button.dataset.active = 'false';
        button.classList.remove('bg-blue-100');
    });
}

function render(combo) {
    const input = combo.querySelector('input[name]');
    const panel = combo.querySelector('[data-miller-panel]');
    const options = combo.querySelector('[data-miller-options]');
    const addButton = combo.querySelector('[data-miller-add]');

    if (!input || input.disabled) {
        closePanel(combo);
        return;
    }

    const query = input.value.trim();
    const lowerQuery = query.toLowerCase();
    const list = millerCache ?? [];

    const matches = lowerQuery
        ? list.filter((miller) =>
              String(miller.name).toLowerCase().includes(lowerQuery),
          )
        : list;

    const hasExactMatch =
        lowerQuery !== '' &&
        list.some((miller) => String(miller.name).toLowerCase() === lowerQuery);

    options.replaceChildren(...matches.map(createOptionButton));

    const showAdd = query !== '' && !hasExactMatch;
    addButton.hidden = !showAdd;
    addButton.textContent = showAdd ? `Add "${query}"` : '';

    const showPanel = matches.length > 0 || showAdd;
    panel.classList.toggle('hidden', !showPanel);
    input.setAttribute('aria-expanded', String(showPanel));
}

function selectValue(combo, value) {
    const input = combo.querySelector('input[name]');

    input.value = value;
    // The dispatched input event re-renders the panel, so close it last —
    // a selected option must never stay visible.
    input.dispatchEvent(new Event('input', { bubbles: true }));
    closePanel(combo);
}

function moveActive(combo, delta) {
    const optionButtons = Array.from(
        combo.querySelectorAll('[data-miller-option]'),
    );
    if (optionButtons.length === 0) {
        return;
    }

    const currentIndex = optionButtons.findIndex(
        (button) => button.dataset.active === 'true',
    );
    let nextIndex = currentIndex + delta;
    if (nextIndex < 0) {
        nextIndex = optionButtons.length - 1;
    } else if (nextIndex >= optionButtons.length) {
        nextIndex = 0;
    }

    optionButtons.forEach((button, index) => {
        const isActive = index === nextIndex;
        button.dataset.active = String(isActive);
        button.classList.toggle('bg-blue-100', isActive);
        if (isActive) {
            button.scrollIntoView({ block: 'nearest' });
        }
    });
}

function activeOption(combo) {
    return combo.querySelector('[data-miller-option][data-active="true"]');
}

function showDialogError(message) {
    const errorBox = getDialog()?.querySelector('[data-miller-dialog-error]');
    if (!errorBox) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, 'error');
        } else {
            console.error(message);
        }
        return;
    }

    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
}

function openDialog(combo, query) {
    const dialog = getDialog();
    if (!dialog) {
        const msg = 'The miller profile form is unavailable on this page.';
        if (typeof window.showAlert === 'function') {
            window.showAlert(msg, 'warning');
        } else {
            console.warn(msg);
        }
        return;
    }

    activeCombo = combo;

    dialog.querySelector('[data-miller-name]').value = query;
    dialog.querySelector('[data-miller-category]').value = '';
    dialog.querySelector('[data-miller-capacity]').value = '';

    const errorBox = dialog.querySelector('[data-miller-dialog-error]');
    errorBox.textContent = '';
    errorBox.classList.add('hidden');

    closePanel(combo);
    dialog.showModal();
    dialog.querySelector('[data-miller-name]').focus();
}

async function saveMiller() {
    const dialog = getDialog();
    if (!dialog || !activeCombo) {
        return;
    }

    const nameInput = dialog.querySelector('[data-miller-name]');
    const categoryInput = dialog.querySelector('[data-miller-category]');
    const capacityInput = dialog.querySelector('[data-miller-capacity]');
    const saveButton = dialog.querySelector('[data-miller-dialog-save]');

    const name = nameInput.value.trim();
    const category = categoryInput.value;
    const capacity = capacityInput.value.trim();

    if (!name) {
        showDialogError('Miller name is required.');
        nameInput.focus();
        return;
    }
    if (!category) {
        showDialogError('Select a category: NFA Owned or Private.');
        categoryInput.focus();
        return;
    }
    if (capacity === '' || Number.isNaN(Number(capacity)) || Number(capacity) < 0) {
        showDialogError('Enter a valid milling capacity in bags.');
        capacityInput.focus();
        return;
    }

    saveButton.disabled = true;

    try {
        const response = await fetch(activeCombo.dataset.storeUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken() ?? '',
            },
            body: JSON.stringify({
                name,
                category,
                capacity_12h_bags: capacity,
            }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstError = Object.values(data.errors || {}).flat()[0];
            throw new Error(
                firstError ||
                    data.message ||
                    'The miller profile could not be saved.',
            );
        }

        upsertMiller(data);

        const input = activeCombo.querySelector('input[name]');
        if (input) {
            input.value = data.name;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            closePanel(activeCombo);
        }

        dialog.close();
    } catch (error) {
        showDialogError(error.message);
    } finally {
        saveButton.disabled = false;
    }
}

function initMillerCombobox() {
    document.addEventListener('focusin', (event) => {
        const input = event.target.closest?.('[data-miller-combobox] input[name]');
        if (!input || input.disabled) {
            return;
        }

        const combo = input.closest('[data-miller-combobox]');
        loadMillers(combo).then(() => {
            if (combo.contains(document.activeElement)) {
                render(combo);
            }
        });
    });

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('[data-miller-combobox] input[name]');
        if (!input || input.disabled) {
            return;
        }

        render(input.closest('[data-miller-combobox]'));
    });

    document.addEventListener('focusout', (event) => {
        const input = event.target.closest?.('[data-miller-combobox] input[name]');
        if (!input) {
            return;
        }

        requestAnimationFrame(() => {
            const combo = input.closest('[data-miller-combobox]');
            if (combo && !combo.contains(document.activeElement)) {
                closePanel(combo);
            }
        });
    });

    // Keep focus on the input while clicking dropdown buttons, so the panel
    // does not close between mousedown and click.
    document.addEventListener('mousedown', (event) => {
        if (
            event.target.closest?.('[data-miller-combobox] [data-miller-panel]') &&
            event.target.closest('button')
        ) {
            event.preventDefault();
        }
    });

    document.addEventListener('click', (event) => {
        const optionButton = event.target.closest?.(
            '[data-miller-combobox] [data-miller-option]',
        );
        if (optionButton) {
            selectValue(
                optionButton.closest('[data-miller-combobox]'),
                optionButton.dataset.millerName,
            );
            return;
        }

        const addButton = event.target.closest?.(
            '[data-miller-combobox] [data-miller-add]',
        );
        if (addButton && !addButton.hidden) {
            const combo = addButton.closest('[data-miller-combobox]');
            openDialog(combo, combo.querySelector('input[name]').value.trim());
            return;
        }

        if (!event.target.closest?.('[data-miller-combobox]')) {
            document
                .querySelectorAll('[data-miller-combobox]')
                .forEach((combo) => closePanel(combo));
        }
    });

    document.addEventListener('keydown', (event) => {
        const input = event.target.closest?.('[data-miller-combobox] input[name]');
        if (!input || input.disabled) {
            return;
        }

        const combo = input.closest('[data-miller-combobox]');
        const panel = combo.querySelector('[data-miller-panel]');

        if (event.key === 'Escape') {
            closePanel(combo);
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (panel.classList.contains('hidden')) {
                render(combo);
            }
            moveActive(combo, event.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        const current = activeOption(combo);
        if (current) {
            event.preventDefault();
            selectValue(combo, current.dataset.millerName);
            return;
        }

        if (panel.classList.contains('hidden')) {
            return;
        }

        const query = input.value.trim().toLowerCase();
        const exactOption = Array.from(
            combo.querySelectorAll('[data-miller-option]'),
        ).find((button) => button.dataset.millerName.toLowerCase() === query);
        if (exactOption) {
            event.preventDefault();
            selectValue(combo, exactOption.dataset.millerName);
            return;
        }

        const addButton = combo.querySelector('[data-miller-add]');
        if (addButton && !addButton.hidden) {
            event.preventDefault();
            openDialog(combo, input.value.trim());
        }
    });

    const dialog = getDialog();
    if (!dialog) {
        return;
    }

    dialog.querySelectorAll('[data-miller-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog
        .querySelector('[data-miller-dialog-save]')
        ?.addEventListener('click', saveMiller);

    // Enter inside the popup must save the profile, never submit the host form.
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('input')) {
            event.preventDefault();
            saveMiller();
        }
    });
}

initMillerCombobox();
