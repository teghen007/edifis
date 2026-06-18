<?php

use App\Livewire\Field\AttendanceScanner;
use App\Livewire\Field\IssuanceWorkstation;
use Illuminate\Support\Facades\Route;

// Node-first field workflows — served on campus (.local), also available on cloud
Route::get('/field/attendance', AttendanceScanner::class)
    ->middleware(['auth', 'role:class_master|subject_teacher'])
    ->name('field.attendance');

Route::get('/field/issuance', IssuanceWorkstation::class)
    ->middleware(['auth', 'role:bursar'])
    ->name('field.issuance');
