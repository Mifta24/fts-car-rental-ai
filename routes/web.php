<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\RentalSwitchController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\VehicleInventoryController;
use App\Http\Controllers\Admin\VehicleMaintenanceController;
use App\Http\Controllers\AgentChatController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingLookupController;
use App\Http\Controllers\RentalPageController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RentalPageController::class, 'index'])->name('home');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('rental', RentalSwitchController::class)->name('rental.switch');

        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/{vehicle}/inventory', [VehicleInventoryController::class, 'index'])->name('vehicles.inventory');
        Route::get('vehicles/{vehicle}/maintenance', [VehicleMaintenanceController::class, 'index'])->name('vehicles.maintenance.index');
        Route::get('knowledge-items', [KnowledgeItemController::class, 'index'])->name('knowledge-items.index');
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');

        Route::middleware('can:write-rental')->group(function () {
            Route::put('vehicles/{vehicle}/inventory', [VehicleInventoryController::class, 'update'])->name('vehicles.inventory.update');
            Route::post('vehicles/{vehicle}/maintenance', [VehicleMaintenanceController::class, 'store'])->name('vehicles.maintenance.store');
            Route::put('vehicles/{vehicle}/maintenance/{maintenance}', [VehicleMaintenanceController::class, 'update'])->name('vehicles.maintenance.update');
            Route::post('vehicles/{vehicle}/maintenance/{maintenance}/complete', [VehicleMaintenanceController::class, 'complete'])->name('vehicles.maintenance.complete');
            Route::delete('vehicles/{vehicle}/maintenance/{maintenance}', [VehicleMaintenanceController::class, 'destroy'])->name('vehicles.maintenance.destroy');
            Route::resource('vehicles', VehicleController::class)->except(['index', 'show']);
            Route::resource('knowledge-items', KnowledgeItemController::class)->except(['index', 'show']);

            Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');

            Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
            Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
        });

        Route::middleware('can:manage-rental')->group(function () {
            Route::get('team', [TeamController::class, 'index'])->name('team.index');
            Route::post('team', [TeamController::class, 'store'])->name('team.store');
            Route::patch('team/{member}', [TeamController::class, 'update'])->name('team.update');
            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        });
    });
});

Route::prefix('{rentalSlug}')->group(function () {
    Route::get('/', [RentalPageController::class, 'show'])->name('rental.show');
    Route::get('vehicles', [RentalPageController::class, 'vehicles'])->name('rental.vehicles');
    Route::get('vehicles/{vehicleSlug}', [RentalPageController::class, 'vehicle'])->name('rental.vehicle');
    Route::get('services', [RentalPageController::class, 'services'])->name('rental.services');
    Route::get('services/{serviceId}', [RentalPageController::class, 'service'])->whereNumber('serviceId')->name('rental.service');
    Route::get('info', [RentalPageController::class, 'info'])->name('rental.info');
    Route::get('staff', [RentalPageController::class, 'staff'])->name('rental.staff');
    Route::get('reservation', [RentalPageController::class, 'reservationScene'])->name('rental.reservation');
    Route::get('booking', [RentalPageController::class, 'booking'])->name('rental.booking');

    Route::prefix('booking')->name('booking.')->middleware('throttle:10,1')->group(function () {
        Route::post('lookup', [BookingLookupController::class, 'lookup'])->name('lookup');
        Route::post('cancel', [BookingLookupController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('reservation')->name('reservation.')->middleware('throttle:20,1')->group(function () {
        Route::post('quote', [ReservationController::class, 'quote'])->name('quote');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
    });

    Route::prefix('agent')->name('agent.')->group(function () {
        Route::post('start', [AgentChatController::class, 'start'])->middleware('throttle:agent-start')->name('start');
        Route::post('message', [AgentChatController::class, 'message'])->middleware('throttle:agent-message')->name('message');
        Route::get('history', [AgentChatController::class, 'history'])->middleware('throttle:agent-history')->name('history');
    });
});
