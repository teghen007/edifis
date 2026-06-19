<?php

use App\Http\Controllers\SchoolHomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchoolHomeController::class, 'index'])->name('school.home');
