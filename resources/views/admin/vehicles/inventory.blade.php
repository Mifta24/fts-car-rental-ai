<x-admin-layout :title="'Availability — '.$vehicle->name">
    <x-slot name="actions">
        <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-slate-600 hover:underline">← Vehicles</a>
    </x-slot>

    @if (! $lastOpenDay || $lastOpenDay->lt(now($rental->timezone)->addDays(14)))
        <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ $lastOpenDay ? 'Open dates end on '.$lastOpenDay->format('j M Y').'. Customers cannot book beyond that day.' : 'No upcoming dates are open yet, so this car cannot be booked.' }}
        </div>
    @endif

    @can('write-rental')
    <form method="POST" action="{{ route('admin.vehicles.inventory.update', $vehicle) }}" class="mb-8 rounded-xl border border-slate-200 bg-white p-5">
        @csrf @method('PUT')
        <p class="mb-4 font-medium text-slate-900">Open or update a date range</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <label class="text-sm text-slate-600">From
                <input type="date" name="start_date" value="{{ old('start_date', now($rental->timezone)->toDateString()) }}" min="{{ now($rental->timezone)->toDateString() }}" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm text-slate-600">Until (inclusive)
                <input type="date" name="end_date" value="{{ old('end_date', now($rental->timezone)->addDays(89)->toDateString()) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                @error('end_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm text-slate-600">Units available per day
                <input type="number" name="total_units" min="0" max="500" value="{{ old('total_units', 1) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                @error('total_units') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="text-sm text-slate-600">Price per day ({{ $rental->currency }})
                <input type="number" name="price" min="0" step="1000" value="{{ old('price', (int) $vehicle->base_price) }}" required class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                @error('price') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
        <fieldset class="mt-4">
            <legend class="text-sm text-slate-600">Only on these days (leave empty for every day)</legend>
            <div class="mt-1 flex flex-wrap gap-3 text-sm text-slate-700">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $number => $label)
                    <label class="inline-flex items-center gap-1"><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array($number, array_map('intval', old('weekdays', [])), true))> {{ $label }}</label>
                @endforeach
            </div>
        </fieldset>
        <p class="mt-3 text-xs text-slate-500">Units already booked are never removed: a day with 2 booked keeps at least 2 units.</p>
        <button type="submit" class="mt-4 rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save dates</button>
    </form>
    @endcan

    <div class="mb-3 flex items-center justify-between text-sm">
        <p class="text-slate-600">{{ $from->format('j M Y') }} – {{ $from->addDays($calendar->count() - 1)->format('j M Y') }}</p>
        <div class="flex gap-4">
            <a href="{{ route('admin.vehicles.inventory', [$vehicle, 'from' => $from->subDays($calendar->count())->toDateString()]) }}" class="text-slate-600 hover:underline">← Earlier</a>
            <a href="{{ route('admin.vehicles.inventory', [$vehicle, 'from' => $from->addDays($calendar->count())->toDateString()]) }}" class="text-slate-600 hover:underline">Later →</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Price</th><th class="px-4 py-2">Units</th><th class="px-4 py-2">Booked</th><th class="px-4 py-2">In workshop</th><th class="px-4 py-2">Free</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($calendar as $entry)
                    @php($day = $entry['day'])
                    <tr class="{{ $day ? '' : 'text-slate-400' }}">
                        <td class="px-4 py-2">{{ $entry['date']->format('D j M') }}</td>
                        @if ($day)
                            <td class="px-4 py-2">{{ number_format((float) $day->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-2">{{ $day->total_units }}</td>
                            <td class="px-4 py-2">{{ $day->booked_units }}</td>
                            <td class="px-4 py-2">{{ $day->blocked_units ?: '—' }}</td>
                            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $day->isAvailable() ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $day->availableUnits() }}</span></td>
                        @else
                            <td class="px-4 py-2" colspan="5">Not open for booking</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
