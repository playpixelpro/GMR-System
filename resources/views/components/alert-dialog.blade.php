{{-- Interactive Alert Box Dialog Modal to replace window.alert() --}}
<div id="app-alert-box-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-xs p-4 transition-opacity duration-200"
     role="dialog"
     aria-modal="true"
     aria-labelledby="app-alert-box-title"
     aria-describedby="app-alert-box-message">
    <div id="app-alert-box-panel"
         class="relative w-full max-w-md rounded-2xl bg-base-100 p-6 shadow-2xl border border-base-content/10 transition-all duration-200 transform scale-95 opacity-0">
        <div class="flex items-start gap-3.5">
            <div id="app-alert-box-icon-container" class="size-11 shrink-0 rounded-xl flex items-center justify-center bg-warning/15 text-warning">
                <span id="app-alert-box-icon" class="icon-[tabler--alert-triangle] size-6"></span>
            </div>
            <div class="flex-1 min-w-0 pt-0.5">
                <h3 id="app-alert-box-title" class="text-base font-bold text-base-content leading-tight">Notice</h3>
                <div id="app-alert-box-message" class="mt-2 text-sm text-base-content/80 whitespace-pre-line leading-relaxed"></div>
            </div>
            <button type="button"
                    id="app-alert-box-close-btn"
                    class="btn btn-circle btn-text btn-xs text-base-content/50 hover:text-base-content shrink-0 cursor-pointer"
                    aria-label="Close alert dialog">
                <span class="icon-[tabler--x] size-4"></span>
            </button>
        </div>
        <div class="mt-6 flex justify-end gap-2" id="app-alert-box-actions">
            <button type="button"
                    id="app-alert-box-ok-btn"
                    class="btn btn-primary btn-sm px-6 font-semibold cursor-pointer">
                OK
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('app-alert-box-modal');
    const panel = document.getElementById('app-alert-box-panel');
    const iconContainer = document.getElementById('app-alert-box-icon-container');
    const icon = document.getElementById('app-alert-box-icon');
    const titleEl = document.getElementById('app-alert-box-title');
    const messageEl = document.getElementById('app-alert-box-message');
    const closeBtn = document.getElementById('app-alert-box-close-btn');
    const okBtn = document.getElementById('app-alert-box-ok-btn');

    if (!modal || !panel) return;

    let currentResolve = null;

    const themeConfig = {
        success: {
            title: 'Success',
            icon: 'icon-[tabler--circle-check]',
            containerClass: 'bg-success/15 text-success',
            btnClass: 'btn-success text-success-content',
        },
        error: {
            title: 'Error',
            icon: 'icon-[tabler--alert-circle]',
            containerClass: 'bg-error/15 text-error',
            btnClass: 'btn-error text-error-content',
        },
        warning: {
            title: 'Attention',
            icon: 'icon-[tabler--alert-triangle]',
            containerClass: 'bg-warning/15 text-warning',
            btnClass: 'btn-warning text-warning-content',
        },
        info: {
            title: 'Notice',
            icon: 'icon-[tabler--info-circle]',
            containerClass: 'bg-info/15 text-info',
            btnClass: 'btn-primary text-primary-content',
        }
    };

    function closeAlertBox() {
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            if (currentResolve) {
                const res = currentResolve;
                currentResolve = null;
                res(true);
            }
        }, 150);
    }

    function showAlert(message, type = 'info', title = null) {
        return new Promise((resolve) => {
            currentResolve = resolve;

            const normalizedType = ['success', 'error', 'danger', 'warning', 'info'].includes(type)
                ? (type === 'danger' ? 'error' : type)
                : 'info';

            const config = themeConfig[normalizedType] || themeConfig.info;

            titleEl.textContent = title || config.title;
            messageEl.textContent = message || '';

            icon.className = config.icon + ' size-6';
            iconContainer.className = 'size-11 shrink-0 rounded-xl flex items-center justify-center ' + config.containerClass;
            okBtn.className = 'btn btn-sm px-6 font-semibold cursor-pointer ' + config.btnClass;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            requestAnimationFrame(() => {
                panel.classList.remove('scale-95', 'opacity-0');
                panel.classList.add('scale-100', 'opacity-100');
                okBtn.focus();
            });
        });
    }

    okBtn?.addEventListener('click', closeAlertBox);
    closeBtn?.addEventListener('click', closeAlertBox);

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeAlertBox();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!modal.classList.contains('hidden')) {
            if (e.key === 'Escape' || e.key === 'Enter') {
                e.preventDefault();
                closeAlertBox();
            }
        }
    });

    window.showAlert = showAlert;
    window.alertBox = showAlert;

    // Direct override so any native window.alert call displays the custom Alert Box instead
    window.alert = function (message) {
        return showAlert(message, 'warning');
    };
})();
</script>
