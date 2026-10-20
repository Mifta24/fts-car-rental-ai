<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('addons')->nullable()->after('with_driver');
            $table->decimal('duration_discount', 12, 2)->default(0)->after('addons');
            $table->string('promo_code')->nullable()->after('duration_discount');
            $table->decimal('promo_discount', 12, 2)->default(0)->after('promo_code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['addons', 'duration_discount', 'promo_code', 'promo_discount']);
        });
    }
};
