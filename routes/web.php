<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HandoverController;
use App\Http\Controllers\Admin\KnowledgeItemController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\AgentChatController;
use App\Http\Controllers\Auth\LoginController;
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

        Route::resource('vehicles', VehicleController::class)->except('show');
        Route::resource('knowledge-items', KnowledgeItemController::class)->except('show');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');

        Route::get('handovers', [HandoverController::class, 'index'])->name('handovers.index');
        Route::get('handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
        Route::post('handovers/{handover}/reply', [HandoverController::class, 'reply'])->name('handovers.reply');
        Route::post('handovers/{handover}/resolve', [HandoverController::class, 'resolve'])->name('handovers.resolve');
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
