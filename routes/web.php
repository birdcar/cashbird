<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth:workos')->group(function () {
    Route::get('/dashboard', function () {
        return view('livewire.dashboard');
    })->name('dashboard');
});
