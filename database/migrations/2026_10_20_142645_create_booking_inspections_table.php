<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('odometer_km');
            $table->unsignedTinyInteger('fuel_percent');
            $table->text('condition_notes')->nullable();
            $table->json('photos')->nullable();
            $table->string('signed_by');
            $table->string('signature_path')->nullable();
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->decimal('damage_fee', 12, 2)->default(0);
            $table->text('fee_notes')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_inspections');
    }
};
