<x-admin-layout title="Bookings">
    <div class="mb-4 flex gap-2 text-sm">
        @foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $value => $label)
            <a href="{{ route('admin.bookings.index', $value ? ['status' => $value] : []) }}"
                class="rounded-full px-3 py-1 {{ $status === $value || (! $status && $value === '') ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Vehicle</th>
                    <th class="px-4 py-3">Rental period</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $booking->reference }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $booking->customer_name }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->contact_type ? ucfirst($booking->contact_type).': ' : '' }}{{ $booking->customer_phone ?? $booking->customer_email }}</p>
                            @if ($booking->notes)<p class="mt-1 max-w-xs text-xs italic text-slate-400">{{ $booking->notes }}</p>@endif
                        </td>
                        <td class="px-4 py-3">{{ $booking->unit_count }} × {{ $booking->vehicle->name }}<p class="text-xs text-slate-400">{{ $booking->passengers }} passengers{{ $booking->with_driver ? ' · with driver' : ' · self-drive' }}</p></td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $booking->pickup_date->toFormattedDateString() }} → {{ $booking->return_date->toFormattedDateString() }}</td>
                        <td class="px-4 py-3">
                            {{ $rental->currency }} {{ number_format((float) $booking->total_price, 0, ',', '.') }}
                            @foreach ($booking->addons ?? [] as $extra)<p class="text-xs text-slate-400">+ {{ $extra['name'] }} ({{ number_format((float) $extra['total'], 0, ',', '.') }})</p>@endforeach
                            @if ((float) $booking->duration_discount > 0)<p class="text-xs text-slate-400">− long rental {{ number_format((float) $booking->duration_discount, 0, ',', '.') }}</p>@endif
                            @if ($booking->promo_code)<p class="text-xs text-slate-400">− {{ $booking->promo_code }} {{ number_format((float) $booking->promo_discount, 0, ',', '.') }}</p>@endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-orange-100 text-orange-700' => $booking->status === 'pending',
                                'bg-emerald-100 text-emerald-700' => $booking->status === 'confirmed',
                                'bg-slate-100 text-slate-500' => in_array($booking->status, ['cancelled', 'expired'], true),
                            ])>{{ ucfirst($booking->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('write-rental')
                            @if (in_array($booking->status, ['pending', 'cancelled', 'expired'], true))
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="text-emerald-600 hover:underline">{{ $booking->status === 'pending' ? 'Confirm' : 'Reopen & confirm' }}</button>
                                </form>
                            @endif
                            @if (in_array($booking->status, ['pending', 'confirmed'], true))
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Cancel</button>
                                </form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No bookings found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
</x-admin-layout>
