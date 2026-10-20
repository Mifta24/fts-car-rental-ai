<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duration_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_days');
            $table->decimal('percent', 5, 2);
            $table->timestamps();

            $table->unique(['rental_id', 'min_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duration_discounts');
    }
};
