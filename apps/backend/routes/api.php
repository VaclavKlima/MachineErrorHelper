<?php

use App\Http\Controllers\Api\DiagnosisController;
use App\Http\Controllers\Api\MachineController;
use Illuminate\Support\Facades\Route;

Route::get('machines', [MachineController::class, 'index'])->name('api.machines.index');
Route::get('machines/{machine:slug}', [MachineController::class, 'show'])->name('api.machines.show');

Route::post('diagnoses', [DiagnosisController::class, 'store'])->name('api.diagnoses.store');
Route::get('diagnoses/{diagnosis}', [DiagnosisController::class, 'show'])->name('api.diagnoses.show');
Route::post('diagnoses/{diagnosis}/confirm-code', [DiagnosisController::class, 'confirmCode'])->name('api.diagnoses.confirm-code');
Route::post('diagnoses/{diagnosis}/manual-code', [DiagnosisController::class, 'manualCode'])->name('api.diagnoses.manual-code');
