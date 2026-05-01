<?php

use Illuminate\Support\Facades\Route;

// Placeholder — extend in later phases (forgot password, email verify notice, etc.)

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');
});
