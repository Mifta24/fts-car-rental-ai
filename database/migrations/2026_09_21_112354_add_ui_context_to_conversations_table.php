<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('current_scene', 20)->default('reception')->after('status');
            $table->foreignId('selected_vehicle_id')->nullable()->after('current_scene')->constrained('vehicles')->nullOnDelete();
            $table->json('reservation_state')->nullable()->after('selected_vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_vehicle_id');
            $table->dropColumn(['current_scene', 'reservation_state']);
        });
    }
};
