<x-admin-layout title="Pricing & extras">
    @php
        $field = 'rounded-md border border-slate-300 px-2 py-1 text-sm';
        $canWrite = auth()->user()->can('write-rental');
    @endphp

    <p class="mb-6 max-w-2xl text-sm text-slate-500">
        Daily car prices — including weekend and seasonal rates — are set per date on each vehicle's <em>Availability</em> page.
        Here you manage what is added on top of or taken off that price.
    </p>

    {{-- Extras --}}
    <section class="mb-10">
        <h2 class="mb-1 text-lg font-medium text-slate-900">Extras</h2>
        <p class="mb-3 text-sm text-slate-500">Child seat, GPS, ETC card, insurance upgrade, airport delivery, one-way drop-off fee… <strong>Per day</strong> = price × days × cars; <strong>per booking</strong> = one flat amount.</p>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">English</th><th class="px-3 py-2">日本語</th><th class="px-3 py-2">Price ({{ $rental->currency }})</th><th class="px-3 py-2">Charged</th><th class="px-3 py-2">Active</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($addons as $addon)
                        <tr>
                            <td class="px-3 py-2"><input form="addon-{{ $addon->id }}" name="name" value="{{ $addon->name }}" required maxlength="120" class="{{ $field }} w-36" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="addon-{{ $addon->id }}" name="translations[en][name]" value="{{ $addon->translations['en']['name'] ?? '' }}" maxlength="120" class="{{ $field }} w-32" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="addon-{{ $addon->id }}" name="translations[ja][name]" value="{{ $addon->translations['ja']['name'] ?? '' }}" maxlength="120" class="{{ $field }} w-32" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="addon-{{ $addon->id }}" type="number" name="price" value="{{ (float) $addon->price }}" min="0" step="any" required class="{{ $field }} w-28" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2">
                                <select form="addon-{{ $addon->id }}" name="charge" class="{{ $field }}" @disabled(! $canWrite)>
                                    @foreach ($charges as $charge)<option value="{{ $charge }}" @selected($addon->charge === $charge)>{{ $charge === 'per_day' ? 'Per day' : 'Per booking' }}</option>@endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2"><input form="addon-{{ $addon->id }}" type="checkbox" name="is_active" value="1" @checked($addon->is_active) @disabled(! $canWrite) aria-label="Active"></td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                @if ($canWrite)
                                    <form id="addon-{{ $addon->id }}" method="POST" action="{{ route('admin.addons.update', $addon) }}" class="inline">@csrf @method('PUT')<button type="submit" class="text-slate-600 hover:underline">Save</button></form>
                                    <form method="POST" action="{{ route('admin.addons.destroy', $addon) }}" class="inline" onsubmit="return confirm('Delete this extra?')">@csrf @method('DELETE')<button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($canWrite)
                        <tr class="bg-slate-50">
                            <td class="px-3 py-2"><input form="addon-new" name="name" placeholder="New extra" required maxlength="120" class="{{ $field }} w-36"></td>
                            <td class="px-3 py-2"><input form="addon-new" name="translations[en][name]" maxlength="120" class="{{ $field }} w-32"></td>
                            <td class="px-3 py-2"><input form="addon-new" name="translations[ja][name]" maxlength="120" class="{{ $field }} w-32"></td>
                            <td class="px-3 py-2"><input form="addon-new" type="number" name="price" min="0" step="any" required class="{{ $field }} w-28"></td>
                            <td class="px-3 py-2"><select form="addon-new" name="charge" class="{{ $field }}"><option value="per_booking">Per booking</option><option value="per_day">Per day</option></select></td>
                            <td class="px-3 py-2"><input form="addon-new" type="checkbox" name="is_active" value="1" checked aria-label="Active"></td>
                            <td class="px-3 py-2 text-right"><form id="addon-new" method="POST" action="{{ route('admin.addons.store') }}">@csrf<button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-sm font-medium text-white hover:bg-slate-700">Add</button></form></td>
                        </tr>
                    @elseif ($addons->isEmpty())
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No extras yet.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        @foreach (['name', 'price', 'charge', 'translations.en.name', 'translations.ja.name'] as $key)@error($key)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @endforeach
    </section>

    {{-- Duration discounts --}}
    <section class="mb-10">
        <h2 class="mb-1 text-lg font-medium text-slate-900">Long-rental discounts</h2>
        <p class="mb-3 text-sm text-slate-500">A percentage off the car price from a number of days on. The highest matching discount applies. Saving the same number of days again replaces it.</p>
        <div class="max-w-xl overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">From days</th><th class="px-3 py-2">Discount</th><th class="px-3 py-2"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($durationDiscounts as $discount)
                        <tr>
                            <td class="px-3 py-2">{{ $discount->min_days }} days or more</td>
                            <td class="px-3 py-2">{{ rtrim(rtrim(number_format((float) $discount->percent, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-3 py-2 text-right">
                                @if ($canWrite)<form method="POST" action="{{ route('admin.duration-discounts.destroy', $discount) }}" class="inline">@csrf @method('DELETE')<button type="submit" class="text-red-600 hover:underline">Delete</button></form>@endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($canWrite)
                        <tr class="bg-slate-50">
                            <td class="px-3 py-2"><input form="discount-new" type="number" name="min_days" min="2" max="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}" required aria-label="From days" class="{{ $field }} w-20"> days</td>
                            <td class="px-3 py-2"><input form="discount-new" type="number" name="percent" min="0.01" max="100" step="any" required aria-label="Percent" class="{{ $field }} w-20"> %</td>
                            <td class="px-3 py-2 text-right"><form id="discount-new" method="POST" action="{{ route('admin.duration-discounts.store') }}">@csrf<button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-sm font-medium text-white hover:bg-slate-700">Save</button></form></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @foreach (['min_days', 'percent'] as $key)@error($key)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @endforeach
    </section>

    {{-- Promo codes --}}
    <section>
        <h2 class="mb-1 text-lg font-medium text-slate-900">Promo codes</h2>
        <p class="mb-3 text-sm text-slate-500">The discount applies to the car price (after any long-rental discount), never to extras. Cancelled and expired bookings give their use back.</p>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Value</th><th class="px-3 py-2">Min days</th><th class="px-3 py-2">Valid from</th><th class="px-3 py-2">Valid until</th><th class="px-3 py-2">Max uses</th><th class="px-3 py-2">Used</th><th class="px-3 py-2">Active</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($promoCodes as $promo)
                        <tr>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" name="code" value="{{ $promo->code }}" required maxlength="40" class="{{ $field }} w-28 font-mono uppercase" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><select form="promo-{{ $promo->id }}" name="discount_type" class="{{ $field }}" @disabled(! $canWrite)>@foreach ($discountTypes as $type)<option value="{{ $type }}" @selected($promo->discount_type === $type)>{{ $type === 'percent' ? '% off' : $rental->currency.' off' }}</option>@endforeach</select></td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="number" name="discount_value" value="{{ (float) $promo->discount_value }}" min="0" step="any" required class="{{ $field }} w-24" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="number" name="min_days" value="{{ $promo->min_days }}" min="1" class="{{ $field }} w-16" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="date" name="valid_from" value="{{ $promo->valid_from?->toDateString() }}" class="{{ $field }}" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="date" name="valid_until" value="{{ $promo->valid_until?->toDateString() }}" class="{{ $field }}" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="number" name="max_uses" value="{{ $promo->max_uses }}" min="1" placeholder="∞" class="{{ $field }} w-20" @disabled(! $canWrite)></td>
                            <td class="px-3 py-2 text-slate-500">{{ $promo->usesCount() }}</td>
                            <td class="px-3 py-2"><input form="promo-{{ $promo->id }}" type="checkbox" name="is_active" value="1" @checked($promo->is_active) @disabled(! $canWrite) aria-label="Active"></td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                @if ($canWrite)
                                    <form id="promo-{{ $promo->id }}" method="POST" action="{{ route('admin.promo-codes.update', $promo) }}" class="inline">@csrf @method('PUT')<button type="submit" class="text-slate-600 hover:underline">Save</button></form>
                                    <form method="POST" action="{{ route('admin.promo-codes.destroy', $promo) }}" class="inline" onsubmit="return confirm('Delete this promo code?')">@csrf @method('DELETE')<button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($canWrite)
                        <tr class="bg-slate-50">
                            <td class="px-3 py-2"><input form="promo-new" name="code" placeholder="NEWCODE" required maxlength="40" class="{{ $field }} w-28 font-mono uppercase"></td>
                            <td class="px-3 py-2"><select form="promo-new" name="discount_type" class="{{ $field }}"><option value="percent">% off</option><option value="fixed">{{ $rental->currency }} off</option></select></td>
                            <td class="px-3 py-2"><input form="promo-new" type="number" name="discount_value" min="0" step="any" required class="{{ $field }} w-24"></td>
                            <td class="px-3 py-2"><input form="promo-new" type="number" name="min_days" value="1" min="1" class="{{ $field }} w-16"></td>
                            <td class="px-3 py-2"><input form="promo-new" type="date" name="valid_from" class="{{ $field }}"></td>
                            <td class="px-3 py-2"><input form="promo-new" type="date" name="valid_until" class="{{ $field }}"></td>
                            <td class="px-3 py-2"><input form="promo-new" type="number" name="max_uses" min="1" placeholder="∞" class="{{ $field }} w-20"></td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2"><input form="promo-new" type="checkbox" name="is_active" value="1" checked aria-label="Active"></td>
                            <td class="px-3 py-2 text-right"><form id="promo-new" method="POST" action="{{ route('admin.promo-codes.store') }}">@csrf<button type="submit" class="rounded-full bg-slate-900 px-3 py-1 text-sm font-medium text-white hover:bg-slate-700">Add</button></form></td>
                        </tr>
                    @elseif ($promoCodes->isEmpty())
                        <tr><td colspan="10" class="px-4 py-6 text-center text-slate-400">No promo codes yet.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        @foreach (['code', 'discount_type', 'discount_value', 'min_days', 'valid_from', 'valid_until', 'max_uses'] as $key)@error($key)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @endforeach
    </section>
</x-admin-layout>
