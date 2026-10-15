<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->unsignedTinyInteger('seats')->default(4);
            $table->unsignedTinyInteger('luggage_capacity')->default(2);
            $table->string('transmission')->default('automatic');
            $table->string('fuel_type')->default('petrol');
            $table->string('body_type')->nullable();
            $table->boolean('insurance_included')->default(false);
            $table->boolean('driver_available')->default(false);
            $table->decimal('driver_price', 12, 2)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['rental_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
