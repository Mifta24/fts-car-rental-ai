<x-admin-layout :title="'Maintenance — '.$vehicle->name">
    <x-slot name="actions">
        <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-slate-500 hover:underline">← Vehicles</a>
    </x-slot>

    <p class="mb-4 max-w-2xl text-sm text-slate-500">
        <strong>Service</strong> and <strong>repair</strong> take the car out of the inventory for their dates, so it cannot be quoted or booked.
        <strong>Tax</strong>, <strong>insurance</strong> and <strong>inspection</strong> track a validity date and warn you on the dashboard {{ $alertWithinDays }} days before it ends.
    </p>

    @can('write-rental')
        @php
            $types = ['service' => 'Service', 'repair' => 'Repair', 'tax' => 'Tax', 'insurance' => 'Insurance', 'inspection' => 'Inspection'];
            $value = fn (string $field) => old($field, $editing?->{$field} instanceof \Carbon\CarbonInterface ? $editing->{$field}->toDateString() : $editing?->{$field});
        @endphp
        <form method="POST" action="{{ $editing ? route('admin.vehicles.maintenance.update', [$vehicle, $editing]) : route('admin.vehicles.maintenance.store', $vehicle) }}" class="mb-8 rounded-xl border border-slate-200 bg-white p-5">
            @csrf
            @if ($editing) @method('PUT') @endif
            <h2 class="mb-3 text-lg font-medium text-slate-900">{{ $editing ? 'Edit record' : 'Add record' }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <label class="text-sm text-slate-600">Type
                    <select name="type" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                        @foreach ($types as $key => $label)
                            <option value="{{ $key }}" @selected($value('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm text-slate-600 sm:col-span-2">Title
                    <input type="text" name="title" value="{{ $value('title') }}" maxlength="255" placeholder="e.g. 20,000 km service" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                </label>
                <label class="text-sm text-slate-600">Starts on <span class="text-xs text-slate-400">(service / repair)</span>
                    <input type="date" name="starts_on" value="{{ $value('starts_on') }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    @error('starts_on') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm text-slate-600">Ends on <span class="text-xs text-slate-400">(last day in workshop)</span>
                    <input type="date" name="ends_on" value="{{ $value('ends_on') }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    @error('ends_on') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm text-slate-600">Valid until <span class="text-xs text-slate-400">(tax / insurance / inspection)</span>
                    <input type="date" name="expires_on" value="{{ $value('expires_on') }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    @error('expires_on') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm text-slate-600">Cost ({{ $rental->currency }})
                    <input type="number" name="cost" min="0" step="any" value="{{ $value('cost') }}" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                    @error('cost') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="text-sm text-slate-600 sm:col-span-2">Notes
                    <input type="text" name="notes" value="{{ $value('notes') }}" maxlength="2000" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5">
                </label>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">{{ $editing ? 'Save changes' : 'Add record' }}</button>
                @if ($editing)<a href="{{ route('admin.vehicles.maintenance.index', $vehicle) }}" class="text-sm text-slate-500 hover:underline">Cancel</a>@endif
            </div>
        </form>
    @endcan

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Details</th>
                    <th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    @php
                        if ($record->blocksInventory()) {
                            [$label, $tone] = $record->completed_at ? ['Done', 'bg-slate-100 text-slate-500']
                                : ($record->ends_on->lt($today) ? ['Overdue', 'bg-rose-100 text-rose-700']
                                : ($record->starts_on->gt($today) ? ['Scheduled', 'bg-sky-100 text-sky-700'] : ['In workshop', 'bg-orange-100 text-orange-700']));
                        } else {
                            $left = (int) $today->diffInDays($record->expires_on, false);
                            [$label, $tone] = $left < 0 ? ['Expired', 'bg-rose-100 text-rose-700']
                                : ($left <= $alertWithinDays ? ['Expires in '.$left.' d', 'bg-amber-100 text-amber-800'] : ['Valid', 'bg-emerald-100 text-emerald-700']);
                        }
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ ucfirst($record->type) }}</td>
                        <td class="px-4 py-3">
                            {{ $record->title ?: '—' }}
                            @if ($record->cost !== null)<p class="text-xs text-slate-400">{{ $rental->currency }} {{ number_format((float) $record->cost, 0, ',', '.') }}</p>@endif
                            @if ($record->notes)<p class="text-xs italic text-slate-400">{{ $record->notes }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $record->blocksInventory() ? $record->starts_on->toFormattedDateString().' → '.$record->ends_on->toFormattedDateString() : 'Until '.$record->expires_on->toFormattedDateString() }}
                        </td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $tone }}">{{ $label }}</span></td>
                        <td class="px-4 py-3 text-right">
                            @can('write-rental')
                                @if ($record->blocksInventory() && ! $record->completed_at)
                                    <form method="POST" action="{{ route('admin.vehicles.maintenance.complete', [$vehicle, $record]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-emerald-600 hover:underline">Mark done</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.vehicles.maintenance.index', [$vehicle, 'edit' => $record->id]) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('admin.vehicles.maintenance.destroy', [$vehicle, $record]) }}" class="inline" onsubmit="return confirm('Delete this record?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No maintenance records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
