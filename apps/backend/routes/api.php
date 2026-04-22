<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\MachineController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->name('api.login');
Route::post('register', [AuthController::class, 'register'])->name('api.register');

Route::middleware(['auth:sanctum', 'app.user'])->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('api.me');
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('user/machines', [MachineController::class, 'userMachines'])->name('api.user-machines.index');
    Route::post('user/machines/{machine}', [MachineController::class, 'attachUserMachine'])->name('api.user-machines.attach');
    Route::delete('user/machines/{machine}', [MachineController::class, 'detachUserMachine'])->name('api.user-machines.detach');

    Route::get('machines', [MachineController::class, 'index'])->name('api.machines.index');
    Route::get('machines/{machine:slug}', [MachineController::class, 'show'])->name('api.machines.show');

    Route::post('diagnoses', [DiagnosisController::class, 'store'])->name('api.diagnoses.store');
    Route::get('diagnoses/history', [DiagnosisController::class, 'history'])->name('api.diagnoses.history');
    Route::get('diagnoses/{diagnosis}', [DiagnosisController::class, 'show'])->name('api.diagnoses.show');
    Route::post('diagnoses/{diagnosis}/confirm-code', [DiagnosisController::class, 'confirmCode'])->name('api.diagnoses.confirm-code');
    Route::post('diagnoses/{diagnosis}/manual-code', [DiagnosisController::class, 'manualCode'])->name('api.diagnoses.manual-code');
});
