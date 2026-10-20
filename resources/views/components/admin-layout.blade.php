<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — {{ auth()->user()?->currentRental()?->name ?? config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-theme antialiased">
    <div class="flex min-h-screen">
        <aside class="admin-side w-60 shrink-0">
            <div class="admin-brand border-b border-slate-200 px-5 py-5">
                <span class="rental-monogram" aria-hidden="true">{{ mb_substr((string) auth()->user()?->currentRental()?->name, 0, 1) ?: 'F' }}</span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ auth()->user()?->currentRental()?->name }}</p>
                    <p class="text-xs text-slate-500">Admin Dashboard{{ auth()->user()?->currentRole() ? ' · '.ucfirst(auth()->user()->currentRole()) : '' }}</p>
                </div>
            </div>
            @if (auth()->user()?->activeRentals()->count() > 1)
                <form method="POST" action="{{ route('admin.rental.switch') }}" class="border-b border-slate-200 px-3 py-3">
                    @csrf
                    <label for="rental-switch" class="mb-1 block text-xs text-slate-500">Rental</label>
                    <select id="rental-switch" name="rental_id" onchange="this.form.submit()" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                        @foreach (auth()->user()->activeRentals() as $switchable)
                            <option value="{{ $switchable->id }}" @selected($switchable->id === auth()->user()->currentRental()?->id)>{{ $switchable->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <nav class="space-y-1 px-3 py-4 text-sm">
                @php
                    $navItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
                        ['route' => 'admin.vehicles.index', 'label' => 'Vehicles'],
                        ['route' => 'admin.knowledge-items.index', 'label' => 'Knowledge base'],
                        ['route' => 'admin.bookings.index', 'label' => 'Bookings'],
                        ['route' => 'admin.handovers.index', 'label' => 'Handovers'],
                    ];

                    if (auth()->user()?->isRentalOwner()) {
                        $navItems[] = ['route' => 'admin.team.index', 'label' => 'Team'];
                        $navItems[] = ['route' => 'admin.audit-log.index', 'label' => 'Audit log'];
                    }
                @endphp
                @foreach ($navItems as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        class="admin-nav-link"
                        @if (request()->routeIs($item['route'].'*')) aria-current="page" @endif
                    >{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-slate-200 p-3">
                @csrf
                <button type="submit" class="admin-nav-link w-full text-left">Log out</button>
            </form>
        </aside>

        <main class="flex-1 overflow-y-auto">
            <div class="mx-auto max-w-5xl px-6 py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-3xl font-medium tracking-tight text-slate-900">{{ $title ?? 'Dashboard' }}</h1>
                    {{ $actions ?? '' }}
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
