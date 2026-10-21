<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Services\Analytics\RentalAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    use ResolvesCurrentRental;

    private const PERIODS = [7, 30, 90];

    public function __construct(private readonly RentalAnalytics $analytics) {}

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);
        $days = in_array((int) $request->query('days'), self::PERIODS, true) ? (int) $request->query('days') : 30;

        return view('admin.analytics.index', [
            'rental' => $rental,
            'days' => $days,
            'periods' => self::PERIODS,
            'report' => $this->analytics->report($rental, $days),
        ]);
    }
}
