@props([
    'type' => 'info', // success, error/danger, warning, info, neutral
    'title' => null,
    'message' => null,
    'dismissible' => false,
    'icon' => null,
    'soft' => true,
    'size' => 'md',
])

@php
    $normalizedType = match ($type) {
        'danger', 'error' => 'error',
        'success' => 'success',
        'warning' => 'warning',
        'neutral' => 'neutral',
        'primary' => 'primary',
        'secondary' => 'secondary',
        default => 'info',
    };

    $defaultIcon = match ($normalizedType) {
        'success' => 'icon-[tabler--circle-check]',
        'error' => 'icon-[tabler--alert-circle]',
        'warning' => 'icon-[tabler--alert-triangle]',
        'neutral' => 'icon-[tabler--info-circle]',
        'primary' => 'icon-[tabler--circle-check]',
        'secondary' => 'icon-[tabler--alert-triangle]',
        default => 'icon-[tabler--info-circle]',
    };

    $iconClass = $icon ?? $defaultIcon;

    $typeClasses = match ($normalizedType) {
        'success' => $soft ? 'alert-soft alert-success text-success' : 'alert-success text-success-content',
        'error' => $soft ? 'alert-soft alert-error text-error' : 'alert-error text-error-content',
        'warning' => $soft ? 'alert-soft alert-warning text-warning' : 'alert-warning text-warning-content',
        'neutral' => $soft ? 'alert-soft alert-neutral text-base-content' : 'alert-neutral text-neutral-content',
        'primary' => $soft ? 'alert-soft alert-primary text-primary' : 'alert-primary text-primary-content',
        'secondary' => $soft ? 'alert-soft alert-secondary text-secondary' : 'alert-secondary text-secondary-content',
        default => $soft ? 'alert-soft alert-info text-info' : 'alert-info text-info-content',
    };

    $sizeClasses = match ($size) {
        'sm' => 'text-xs py-2 px-3 gap-2',
        'lg' => 'text-base p-4 gap-3.5',
        default => 'text-sm p-3.5 gap-2.5',
    };

    $iconSizes = match ($size) {
        'sm' => 'size-4',
        'lg' => 'size-6',
        default => 'size-5',
    };
@endphp

<div {{ $attributes->merge(['class' => "alert {$typeClasses} {$sizeClasses} flex items-start rounded-lg transition-all duration-200"]) }} role="alert">
    @if ($iconClass !== 'none')
        <span class="{{ $iconClass }} {{ $iconSizes }} shrink-0 mt-0.5" aria-hidden="true"></span>
    @endif

    <div class="flex-1 min-w-0">
        @if ($title)
            <div class="font-semibold leading-snug">{{ $title }}</div>
        @endif
        @if ($message)
            <div class="{{ $title ? 'mt-0.5' : '' }} leading-relaxed">{{ $message }}</div>
        @endif
        @if (!empty(trim((string)$slot)))
            <div class="{{ $title || $message ? 'mt-1' : '' }} leading-relaxed">{{ $slot }}</div>
        @endif
    </div>

    @if ($dismissible)
        <button type="button"
                class="btn btn-circle btn-text btn-xs text-current/60 hover:text-current hover:bg-black/5 -my-1 -me-1 ms-auto shrink-0 cursor-pointer"
                aria-label="Dismiss alert"
                onclick="this.closest('.alert')?.remove()">
            <span class="icon-[tabler--x] size-4"></span>
        </button>
    @endif
</div>
