<?php

namespace App\Console\Commands;

use App\Services\Reservation\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-pending')]
#[Description('Expire pending reservation requests nobody answered in time and give their cars back to the inventory')]
class ExpirePendingBookings extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $expired = $reservations->expirePending();

        $this->info("Expired {$expired} pending booking(s).");

        return self::SUCCESS;
    }
}
