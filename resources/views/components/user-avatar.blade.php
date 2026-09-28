@props(['user', 'size' => 'size-8'])

@php
    $initial = strtoupper(substr($user->name ?? '?', 0, 1));
@endphp

<span class="relative inline-grid shrink-0 overflow-hidden rounded-full {{ $size }}">
    @if ($user->avatar_url)
        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
             class="col-start-1 row-start-1 {{ $size }} rounded-full object-cover"
             onerror="this.style.display='none';this.nextElementSibling.style.display='grid';"
             loading="lazy">
        <span class="col-start-1 row-start-1 hidden {{ $size }} place-items-center rounded-full bg-primary text-sm font-semibold text-primary-content">{{ $initial }}</span>
    @else
        <span class="{{ $size }} grid place-items-center rounded-full bg-primary text-sm font-semibold text-primary-content">{{ $initial }}</span>
    @endif
</span>
