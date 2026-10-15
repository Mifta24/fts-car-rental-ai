@props(['name' => 'star'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('driver')
            <circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.2"/><path d="M12 14.2V20M3.8 10.5l6-.6M20.2 10.5l-6-.6"/>
            @break
        @case('delivery')
            <path d="M10.5 13.5 3 11l1-2 8 1 4-5a1.5 1.5 0 0 1 2 2l-5 4 1 8-2 1-2.5-7.5L6 15.5V18l-1.5 1-1-3-3-1 1-1.5h2.5Z"/>
            @break
        @case('insurance')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="m9.2 12 1.9 1.9 3.7-3.8"/>
            @break
        @case('gps')
            <circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            @break
        @case('child')
            <circle cx="12" cy="7" r="3"/><path d="M6.5 20v-3a5.5 5.5 0 0 1 11 0v3M9 20v-2M15 20v-2"/>
            @break
        @case('package')
            <path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5v-9Z"/><path d="m3.5 7.5 8.5 4.5 8.5-4.5M12 12v9"/>
            @break
        @case('place')
            <path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>
            @break
        @case('support')
            <path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19v.5a2.5 2.5 0 0 1-2.5 2.5H13"/>
            @break
        @default
            <path d="M5 16.5h14M5 16.5v-4l1.6-4.5h10.8L19 12.5v4M5 12.5h14"/><circle cx="8" cy="17" r="1.6"/><circle cx="16" cy="17" r="1.6"/>
    @endswitch
</svg>
