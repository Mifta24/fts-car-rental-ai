<x-admin-layout title="Analytics">
    @php
        $money = fn ($n) => $rental->currency.' '.number_format((float) $n, 0, ',', '.');
        $pct = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.').'%';
        $label = fn ($key) => ucwords(str_replace('_', ' ', $key));
        $card = 'rounded-xl border border-slate-200 bg-white p-4';
    @endphp

    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
        @foreach ($periods as $period)
            <a href="{{ route('admin.analytics.index', ['days' => $period]) }}"
                class="rounded-full px-3 py-1 {{ $days === $period ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300 text-slate-600' }}">Last {{ $period }} days</a>
        @endforeach
        <span class="text-xs text-slate-400">{{ $report['from']->format('j M') }} – {{ $report['to']->format('j M Y') }}</span>
    </div>

    <div class="motion-cards grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="{{ $card }}"><p class="text-2xl font-semibold text-slate-900" data-countup="{{ (float) $report['revenue'] }}" data-countup-group=".">{{ $money($report['revenue']) }}</p><p class="text-xs text-slate-500">Confirmed revenue</p></div>
        <div class="{{ $card }}"><p class="text-2xl font-semibold text-slate-900" @if ($report['occupancy'] !== null) data-countup="{{ (float) $report['occupancy'] }}" data-countup-decimals="1" @endif>{{ $pct($report['occupancy']) }}</p><p class="text-xs text-slate-500">Fleet occupancy</p></div>
        <div class="{{ $card }}"><p class="text-2xl font-semibold text-slate-900" @if ($report['conversion'] !== null) data-countup="{{ (float) $report['conversion'] }}" data-countup-decimals="1" @endif>{{ $pct($report['conversion']) }}</p><p class="text-xs text-slate-500">Chat → booking ({{ $report['converted'] }} of {{ $report['conversations'] }} chats)</p></div>
        <div class="{{ $card }}"><p class="text-2xl font-semibold text-slate-900" data-countup="{{ (int) $report['handovers'] }}">{{ $report['handovers'] }}</p><p class="text-xs text-slate-500">Handovers to staff</p></div>
    </div>
    <p class="mt-2 text-xs text-slate-400">Revenue counts bookings made in the period and confirmed since. Occupancy is booked cars ÷ rentable cars on the open days in the period; cars in the workshop are not counted as free.</p>

    <div class="motion-cards mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="{{ $card }}">
            <p class="mb-3 font-medium text-slate-900">Bookings made</p>
            @php($totalBookings = max(1, array_sum($report['bookings'])))
            @foreach ($report['bookings'] as $status => $count)
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span>{{ ucfirst($status) }}</span><span class="text-slate-500">{{ $count }}</span></div>
                    <div class="h-1.5 rounded-full bg-slate-100"><div class="bar-grow h-1.5 rounded-full bg-slate-700" style="width: {{ round($count / $totalBookings * 100) }}%"></div></div>
                </div>
            @endforeach
        </div>

        <div class="{{ $card }}">
            <p class="mb-3 font-medium text-slate-900">Top cars</p>
            @forelse ($report['top_vehicles'] as $vehicle)
                <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                    <span>{{ $vehicle->name }}</span>
                    <span class="text-slate-500">{{ $vehicle->bookings }} booking(s) · {{ $money($vehicle->revenue) }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-slate-400">No bookings in this period.</p>
            @endforelse
        </div>

        <div class="{{ $card }}">
            <p class="mb-1 font-medium text-slate-900">What customers ask the AI about</p>
            <p class="mb-3 text-xs text-slate-400">Knowledge areas the AI looked up. Message text is never read.</p>
            @php($topMax = max(1, (int) $report['topics']->max()))
            @forelse ($report['topics'] as $topic => $count)
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span>{{ $label($topic) }}</span><span class="text-slate-500">{{ $count }}</span></div>
                    <div class="h-1.5 rounded-full bg-slate-100"><div class="bar-grow h-1.5 rounded-full bg-slate-700" style="width: {{ round($count / $topMax * 100) }}%"></div></div>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-slate-400">No knowledge lookups in this period.</p>
            @endforelse
        </div>

        <div class="{{ $card }}">
            <p class="mb-3 font-medium text-slate-900">Why chats go to staff</p>
            @forelse ($report['handover_reasons'] as $reason => $count)
                <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0"><span>{{ $label($reason) }}</span><span class="text-slate-500">{{ $count }}</span></div>
            @empty
                <p class="py-4 text-center text-sm text-slate-400">No handovers in this period.</p>
            @endforelse
            @if ($report['tools']->isNotEmpty())
                <p class="mb-2 mt-5 text-xs font-medium uppercase text-slate-400">AI tool use</p>
                <p class="text-xs text-slate-500">{{ $report['tools']->map(fn ($c, $name) => $label($name).' '.$c)->implode(' · ') }}</p>
            @endif
        </div>
    </div>
</x-admin-layout>
