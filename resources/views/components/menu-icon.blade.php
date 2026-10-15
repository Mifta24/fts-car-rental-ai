@props(['name'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('home')
            <path d="M4 11.5 12 5l8 6.5V19a1 1 0 0 1-1 1h-4.5v-5.5h-5V20H5a1 1 0 0 1-1-1v-7.5Z"/>
            @break
        @case('vehicles')
            <path d="M3 16.5h18M4 16.5v-3.2l1.8-4.3h9.4l3.3 4.3V16.5"/><circle cx="7.5" cy="17.5" r="1.7"/><circle cx="16.5" cy="17.5" r="1.7"/><path d="M9 9l-.8 3.5M13 9v3.5"/>
            @break
        @case('services')
            <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3.5 17.5a1.8 1.8 0 0 0 2.6 2.6l5.8-5.8a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.1-.6-.6-2.1 2.5-2.3Z"/>
            @break
        @case('info')
            <rect x="5" y="3.5" width="14" height="17" rx="2.5"/><path d="M9 3.5V6h6V3.5M8.5 11h7M8.5 14.5h7M8.5 18h4"/>
            @break
        @case('reservation')
            <circle cx="8" cy="8" r="3.5"/><path d="M10.5 10.5 20 20M16 16l2.2-2.2M13.4 13.4l1.8-1.8"/>
            @break
        @case('staff')
            <path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19v.5a2.5 2.5 0 0 1-2.5 2.5H13"/>
            @break
        @default
            <circle cx="12" cy="12" r="8"/>
    @endswitch
</svg>
