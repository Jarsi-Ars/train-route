<?php

use App\Http\Controllers\TrainPathController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrainPathController::class, 'index'])->name('index');
Route::post('/search', [TrainPathController::class, 'search'])->name('search');
