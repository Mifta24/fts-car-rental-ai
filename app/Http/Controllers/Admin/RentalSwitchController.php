<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RentalSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['rental_id' => ['required', 'integer']]);

        $rental = $request->user()->activeRentals()->firstWhere('id', $data['rental_id']);

        abort_if($rental === null, 403);

        $request->session()->put(User::ACTIVE_RENTAL_SESSION_KEY, $rental->id);

        return redirect()->route('admin.dashboard')->with('status', __('admin.rental_switched', ['rental' => $rental->name]));
    }
}
