<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('discount_type');
            $table->decimal('discount_value', 12, 2);
            $table->unsignedSmallInteger('min_days')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['rental_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
