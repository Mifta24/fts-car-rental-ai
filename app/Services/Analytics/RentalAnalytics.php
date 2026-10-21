<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Rental;
use App\Models\VehicleInventory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plain numbers for the admin analytics page. Everything is scoped to one
 * rental and one period ending today (in the rental's timezone).
 */
class RentalAnalytics
{
    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable,
     *     revenue: float, bookings: array<string, int>, occupancy: ?float,
     *     top_vehicles: Collection<int, object{name: string, bookings: int, revenue: float}>,
     *     conversations: int, converted: int, conversion: ?float,
     *     handovers: int, handover_reasons: Collection<string, int>,
     *     topics: Collection<string, int>, tools: Collection<string, int>
     * }
     */
    public function report(Rental $rental, int $days): array
    {
        $to = CarbonImmutable::now($rental->timezone)->endOfDay();
        $from = $to->subDays($days - 1)->startOfDay();

        $start = $from->setTimezone(config('app.timezone'));
        $end = $to->setTimezone(config('app.timezone'));

        $bookings = $rental->bookings()->whereBetween('created_at', [$start, $end]);
        $held = [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED];

        $statusCounts = (clone $bookings)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        $conversationIds = $rental->conversations()->whereBetween('created_at', [$start, $end])->pluck('id');
        $converted = $rental->bookings()->whereIn('conversation_id', $conversationIds)->distinct()->count('conversation_id');

        $handovers = HandoverRequest::whereHas('conversation', fn ($q) => $q->where('rental_id', $rental->id))
            ->whereBetween('created_at', [$start, $end]);

        [$topics, $tools] = $this->aiUsage($rental, $start, $end);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => (float) (clone $bookings)->where('status', Booking::STATUS_CONFIRMED)->sum('total_price'),
            'bookings' => [
                'pending' => (int) ($statusCounts[Booking::STATUS_PENDING] ?? 0),
                'confirmed' => (int) ($statusCounts[Booking::STATUS_CONFIRMED] ?? 0),
                'cancelled' => (int) ($statusCounts[Booking::STATUS_CANCELLED] ?? 0),
                'expired' => (int) ($statusCounts[Booking::STATUS_EXPIRED] ?? 0),
            ],
            'occupancy' => $this->occupancy($rental, $from, $to),
            'top_vehicles' => $rental->bookings()
                ->whereBetween('bookings.created_at', [$start, $end])
                ->whereIn('bookings.status', $held)
                ->join('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
                ->groupBy('bookings.vehicle_id', 'vehicles.name')
                ->orderByDesc('total_bookings')
                ->orderByDesc('total_revenue')
                ->limit(5)
                ->get(['vehicles.name', DB::raw('count(*) as total_bookings'), DB::raw('sum(bookings.total_price) as total_revenue')])
                ->map(fn ($row) => (object) ['name' => $row->name, 'bookings' => (int) $row->total_bookings, 'revenue' => (float) $row->total_revenue]),
            'conversations' => $conversationIds->count(),
            'converted' => $converted,
            'conversion' => $conversationIds->isEmpty() ? null : round($converted / $conversationIds->count() * 100, 1),
            'handovers' => (clone $handovers)->count(),
            'handover_reasons' => (clone $handovers)->select('reason', DB::raw('count(*) as total'))->groupBy('reason')->orderByDesc('total')->pluck('total', 'reason'),
            'topics' => $topics,
            'tools' => $tools,
        ];
    }

    /**
     * Booked share of the units that could be rented on the period's days. A
     * unit in the workshop is not rentable, so it does not count as free.
     */
    private function occupancy(Rental $rental, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $row = VehicleInventory::whereIn('vehicle_id', $rental->vehicles()->withTrashed()->select('id'))
            ->whereDate('rental_date', '>=', $from->toDateString())
            ->whereDate('rental_date', '<=', $to->toDateString())
            ->selectRaw('sum(booked_units) as booked, sum(total_units - blocked_units) as rentable')
            ->first();

        return $row && $row->rentable > 0 ? round(min(100, $row->booked / $row->rentable * 100), 1) : null;
    }

    /**
     * What customers asked the AI about, read from the tools it called (never
     * from message text): knowledge categories searched, and tool usage.
     *
     * @return array{0: Collection<string, int>, 1: Collection<string, int>}
     */
    private function aiUsage(Rental $rental, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $topics = collect();
        $tools = collect();

        ConversationMessage::where('role', ConversationMessage::ROLE_ASSISTANT)
            ->whereNotNull('tool_calls')
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('conversation', fn ($q) => $q->where('rental_id', $rental->id))
            ->select(['id', 'tool_calls'])
            ->chunkById(500, function ($messages) use ($topics, $tools) {
                foreach ($messages as $message) {
                    foreach ($message->tool_calls as $call) {
                        $name = (string) ($call['name'] ?? '');
                        $tools[$name] = ($tools[$name] ?? 0) + 1;

                        if ($name === 'search_knowledge') {
                            $category = (string) ($call['input']['category'] ?? 'general');
                            $topics[$category] = ($topics[$category] ?? 0) + 1;
                        }
                    }
                }
            });

        return [$topics->sortDesc(), $tools->sortDesc()];
    }
}
