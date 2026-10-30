<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FootballController;
Route::get('/{section}', FootballController::class)->where('section', 'status|matches|overview');

Route::post('/control', [FootballController::class, 'control']);
