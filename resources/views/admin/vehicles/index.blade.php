<x-admin-layout title="Vehicles">
    <x-slot name="actions">
        @can('write-rental')
        <a href="{{ route('admin.vehicles.create') }}" class="rounded-full bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Add vehicle
        </a>
        @endcan
    </x-slot>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Base price / day</th>
                    <th class="px-4 py-3">Seats / bags</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($vehicles as $vehicle)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $vehicle->name }}</td>
                        <td class="px-4 py-3">{{ $rental->currency }} {{ number_format((float) $vehicle->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $vehicle->seats }} seats / {{ $vehicle->luggage_capacity }} bags</td>
                        <td class="px-4 py-3">{{ $vehicle->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $vehicle->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $vehicle->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.vehicles.inventory', $vehicle) }}" class="text-slate-600 hover:underline">Availability</a>
                            <a href="{{ route('admin.vehicles.maintenance.index', $vehicle) }}" class="ml-3 text-slate-600 hover:underline">Maintenance</a>
                            @can('write-rental')
                            <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}" class="inline" onsubmit="return confirm('Delete this vehicle?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No vehicles yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
