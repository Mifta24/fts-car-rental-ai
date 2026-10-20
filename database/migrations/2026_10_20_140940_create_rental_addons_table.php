<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('translations')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('charge')->default('per_booking');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_addons');
    }
};
