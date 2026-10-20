@php($label = $type === 'pickup' ? 'Pickup check' : 'Return check')
<x-admin-layout :title="$label.' — '.$booking->reference">
    <x-slot name="actions">
        <a href="{{ route('admin.bookings.index') }}" class="text-sm text-slate-500 hover:underline">← Bookings</a>
    </x-slot>

    <div class="mb-6 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
        <span class="font-medium text-slate-900">{{ $booking->customer_name }}</span>
        <span>{{ $booking->vehicle->name }}</span>
        <span class="text-slate-500">{{ $booking->pickup_date->toFormattedDateString() }} → {{ $booking->return_date->toFormattedDateString() }}</span>
        <span class="ml-auto flex gap-3">
            @foreach (['pickup' => 'Pickup', 'return' => 'Return'] as $tab => $tabLabel)
                <a href="{{ route('admin.bookings.inspection.show', [$booking, $tab]) }}" class="{{ $tab === $type ? 'font-semibold text-slate-900' : 'text-slate-500 hover:underline' }}">{{ $booking->inspection($tab) ? '✓ ' : '' }}{{ $tabLabel }}</a>
            @endforeach
        </span>
    </div>

    @if ($inspection)
        @php($fmt = fn ($n) => number_format((float) $n, 0, ',', '.'))
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 text-sm">
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div><dt class="text-xs text-slate-500">Odometer</dt><dd class="text-lg font-semibold">{{ $fmt($inspection->odometer_km) }} km</dd></div>
                    <div><dt class="text-xs text-slate-500">Fuel</dt><dd class="text-lg font-semibold">{{ $inspection->fuel_percent }}%</dd></div>
                    <div><dt class="text-xs text-slate-500">Completed</dt><dd>{{ $inspection->completed_at->timezone($rental->timezone)->format('Y-m-d H:i') }}<br><span class="text-xs text-slate-500">by {{ $inspection->inspector?->name ?? '—' }}</span></dd></div>
                </dl>
                @if ($type === 'return' && $pickup)
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-slate-600">
                        Driven {{ $fmt($inspection->odometer_km - $pickup->odometer_km) }} km · fuel {{ $pickup->fuel_percent }}% → {{ $inspection->fuel_percent }}%
                        @if ($inspection->fuel_percent < $pickup->fuel_percent)<strong class="text-amber-700">(returned with less fuel)</strong>@endif
                    </p>
                @endif
                @if ($inspection->condition_notes)
                    <div><p class="text-xs text-slate-500">Condition notes</p><p class="whitespace-pre-line">{{ $inspection->condition_notes }}</p></div>
                @endif
                @if ($inspection->extraCharges() > 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900">
                        <p class="font-medium">Extra charges: {{ $rental->currency }} {{ $fmt($inspection->extraCharges()) }}</p>
                        <p class="text-xs">Late {{ $fmt($inspection->late_fee) }} · Damage {{ $fmt($inspection->damage_fee) }}</p>
                        @if ($inspection->fee_notes)<p class="mt-1 text-xs">{{ $inspection->fee_notes }}</p>@endif
                    </div>
                @endif
                @if (! empty($inspection->photos))
                    <div>
                        <p class="mb-2 text-xs text-slate-500">Photos</p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($inspection->photos as $index => $path)
                                <a href="{{ route('admin.inspections.photo', [$inspection, $index]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.inspections.photo', [$inspection, $index]) }}" alt="Photo {{ $index + 1 }}" loading="lazy" class="aspect-[4/3] w-full rounded-lg border border-slate-200 object-cover"></a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                <p class="text-xs text-slate-500">Signed by</p>
                <p class="font-medium text-slate-900">{{ $inspection->signed_by }}</p>
                @if ($inspection->signature_path)<img src="{{ route('admin.inspections.signature', $inspection) }}" alt="Signature of {{ $inspection->signed_by }}" class="mt-3 w-full rounded-lg border border-slate-200 bg-white">@endif
            </div>
        </div>
    @elseif ($blocked)
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-slate-500">{{ $blocked }}</div>
    @elseif (! auth()->user()->can('write-rental'))
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-slate-500">This check has not been done yet.</div>
    @else
        @php($field = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5')
        <form method="POST" action="{{ route('admin.bookings.inspection.store', [$booking, $type]) }}" enctype="multipart/form-data" class="max-w-3xl space-y-5 rounded-xl border border-slate-200 bg-white p-5">
            @csrf
            @if ($type === 'return' && $pickup)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">At pickup: {{ number_format($pickup->odometer_km, 0, ',', '.') }} km, fuel {{ $pickup->fuel_percent }}%.</p>
            @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm text-slate-600">Odometer (km)
                    <input type="number" name="odometer_km" min="{{ $type === 'return' ? $pickup->odometer_km : 0 }}" value="{{ old('odometer_km') }}" required inputmode="numeric" class="{{ $field }}">
                    @error('odometer_km')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                </label>
                <label class="text-sm text-slate-600">Fuel level
                    <select name="fuel_percent" class="{{ $field }}">
                        @foreach ($fuelLevels as $level)<option value="{{ $level }}" @selected((int) old('fuel_percent', 100) === $level)>{{ $level === 0 ? 'Empty' : ($level === 100 ? 'Full' : $level.'%') }}</option>@endforeach
                    </select>
                </label>
            </div>
            <label class="block text-sm text-slate-600">Condition notes <span class="text-xs text-slate-400">(scratches, dents, interior, accessories…)</span>
                <textarea name="condition_notes" rows="3" maxlength="3000" class="{{ $field }}">{{ old('condition_notes') }}</textarea>
            </label>
            <label class="block text-sm text-slate-600">Photos <span class="text-xs text-slate-400">(up to 10, JPG/PNG/WebP, 6 MB each)</span>
                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple capture="environment" class="{{ $field }}">
                @error('photos')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                @error('photos.*')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            @if ($type === 'return')
                <fieldset class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <legend class="px-1 text-sm font-medium text-amber-900">Extra charges</legend>
                    @if ($lateDays > 0)<p class="mb-2 text-xs text-amber-800">Returned {{ $lateDays }} day(s) after the agreed return date.</p>@endif
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="text-sm text-slate-600">Late fee ({{ $rental->currency }})<input type="number" name="late_fee" min="0" step="any" value="{{ old('late_fee') }}" class="{{ $field }}">@error('late_fee')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                        <label class="text-sm text-slate-600">Damage fee ({{ $rental->currency }})<input type="number" name="damage_fee" min="0" step="any" value="{{ old('damage_fee') }}" class="{{ $field }}">@error('damage_fee')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    </div>
                    <label class="mt-3 block text-sm text-slate-600">Reason<input type="text" name="fee_notes" maxlength="1000" value="{{ old('fee_notes') }}" class="{{ $field }}"></label>
                    <p class="mt-2 text-xs text-amber-800">Charges are recorded here and are not added to the booking total.</p>
                </fieldset>
            @endif

            <div class="text-sm text-slate-600">
                <label for="signed_by">Customer name (signing)</label>
                <input id="signed_by" type="text" name="signed_by" maxlength="120" value="{{ old('signed_by', $booking->customer_name) }}" required class="{{ $field }}">
                <div data-signature-pad class="mt-3">
                    <canvas class="h-40 w-full touch-none rounded-lg border border-dashed border-slate-400 bg-white" aria-label="Customer signature"></canvas>
                    <input type="hidden" name="signature">
                    <button type="button" data-signature-clear class="mt-1 text-xs text-slate-500 hover:underline">Clear signature</button>
                </div>
                @error('signature')<span class="text-xs text-red-600">{{ $message === 'The signature field is required.' ? 'The customer must sign.' : $message }}</span>@enderror
            </div>

            <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Save {{ strtolower($label) }}</button>
            <p class="text-xs text-slate-400">A saved report cannot be edited.</p>
        </form>
    @endif
</x-admin-layout>
