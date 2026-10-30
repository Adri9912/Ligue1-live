<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FootballController;
Route::get('/{section}', FootballController::class)->where('section', 'status|matches|overview');

Route::post('/control', [FootballController::class, 'control']);

Route::get('/matches/{id}', [FootballController::class,'detail'])->where('id','[0-9]{1,10}');
Route::get('/players/{kind}', [FootballController::class,'players'])->where('kind','scorers|assists');
Route::get('/clubs/{id}', [FootballController::class,'club'])->where('id','[0-9]{1,10}');
Route::get('/clubs/{id}/lineup', [FootballController::class,'lineup'])->where('id','[0-9]{1,10}');
Route::get('/clubs/{club}/players/{id}', [FootballController::class,'player'])->where(['club'=>'[0-9]{1,10}','id'=>'[0-9]{1,10}']);
Route::get('/retro', [FootballController::class,'retro']);
Route::get('/retro/{year}/{section}', [FootballController::class,'retro'])->where(['year'=>'[0-9]{4}','section'=>'standings|matches|scorers|assists']);

// Cookie chiffré et signé : le compteur reste sur le serveur, jamais dans le navigateur.
Route::middleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)->group(function () {
    Route::get('/refresh', [\App\Http\Controllers\RefreshController::class,'status']);
    Route::post('/refresh', [\App\Http\Controllers\RefreshController::class,'refresh']);
});
