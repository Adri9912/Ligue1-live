<?php
use Illuminate\Support\Facades\Route;
Route::get('/', fn () => response()->json(['application' => 'Ligue 1 Live', 'backend' => 'Laravel', 'migration' => 'en cours']));
