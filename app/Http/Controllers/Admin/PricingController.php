<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\RentalAddon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController extends Controller
{
    use ResolvesCurrentRental;

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        return view('admin.pricing.index', [
            'rental' => $rental,
            'addons' => $rental->addons()->get(),
            'promoCodes' => $rental->promoCodes()->orderBy('code')->get(),
            'durationDiscounts' => $rental->durationDiscounts()->get(),
            'charges' => RentalAddon::CHARGES,
            'discountTypes' => PromoCode::TYPES,
        ]);
    }
}
