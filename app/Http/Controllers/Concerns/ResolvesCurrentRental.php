<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Rental;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesCurrentRental
{
    protected function currentRental(Request $request): Rental
    {
        $rental = $request->user()->currentRental();

        if (! $rental) {
            throw new HttpException(403, 'No rental is linked to this account yet.');
        }

        return $rental;
    }
}
