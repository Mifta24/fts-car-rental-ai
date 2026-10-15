@props(['name'])
<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('seat')
            <path d="M7 4h4a2 2 0 0 1 2 2v7h5l2 3v3H7z"/><path d="M5 20V9a2 2 0 0 1 2-2"/>
            @break
        @case('bag')
            <rect x="4" y="8" width="16" height="12" rx="2.5"/><path d="M9 8V6a3 3 0 0 1 6 0v2M4 13h16"/>
            @break
        @case('gearbox')
            <circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>
            @break
        @case('fuel')
            <path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h14M15 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V8l-3-3"/><path d="M8 7h4"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="2.5"/><path d="M8 3v4M16 3v4M4 10h16"/>
            @break
        @case('body')
            <path d="M3 16.5h18M4 16.5v-3.2l1.8-4.3h9.4l3.3 4.3V16.5"/><circle cx="7.5" cy="17.5" r="1.7"/><circle cx="16.5" cy="17.5" r="1.7"/>
            @break
        @case('shield')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="m9.2 12 1.9 1.9 3.7-3.8"/>
            @break
        @case('driver')
            <circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="2.2"/><path d="M12 14.2V20M3.8 10.5l6-.6M20.2 10.5l-6-.6"/>
            @break
        @default
            <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
