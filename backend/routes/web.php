<?php

use App\Http\Controllers\Api\SeedStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Deployment diagnostics for the optional demo dataset. Local-only: the
// controller aborts 404 outside the local environment, so this is never
// reachable on a hosted deployment.
Route::get('/seed-status', SeedStatusController::class);

// Unauthenticated deployment probe for the hosted free tier, which has no
// shell access. Reports only the flag state, the seeder marker and two counts,
// so it is safe to expose. Used to tell "flag off" from "seeder failed".
Route::get('/demo-probe', [SeedStatusController::class, 'probe']);
