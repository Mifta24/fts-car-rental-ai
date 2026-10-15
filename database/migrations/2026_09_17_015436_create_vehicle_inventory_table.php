<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mock reservation-system data: the one table the AI is NEVER allowed to
     * answer from memory. A per-rental PMS/booking-engine adapter replaces
     * this table later without touching the AI or knowledge-base layers.
     */
    public function up(): void
    {
        Schema::create('vehicle_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('rental_date');
            $table->unsignedSmallInteger('total_units');
            $table->unsignedSmallInteger('booked_units')->default(0);
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['vehicle_id', 'rental_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inventory');
    }
};
