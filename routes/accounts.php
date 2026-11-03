<?php
use App\Http\Controllers\AccountController as Accounts;
use App\Http\Middleware\EnsureAccountsEnabled;
use Illuminate\Support\Facades\Route;
// Le groupe web fournit sessions et CSRF, uniquement aux nouveaux endpoints de comptes.
Route::prefix('api/account')->middleware('auth.session')->group(function() {
    Route::get('/session',[Accounts::class,'session']);
    Route::middleware(EnsureAccountsEnabled::class)->group(function() {
        Route::post('/register',[Accounts::class,'register'])->middleware('throttle:account-register');
        Route::post('/login',[Accounts::class,'login'])->middleware('throttle:account-login');
        Route::post('/forgot-password',[Accounts::class,'forgot'])->middleware('throttle:account-mail');
        Route::post('/reset-password',[Accounts::class,'reset'])->middleware('throttle:account-login');
        Route::middleware('auth')->group(function() {
            Route::prefix('admin')->middleware([\App\Http\Middleware\EnsureAdministrator::class,'throttle:60,1'])->group(function() {
                Route::get('/',[\App\Http\Controllers\AdminController::class,'overview']);
                Route::get('/users',[\App\Http\Controllers\AdminController::class,'users']);
                Route::patch('/users/{id}/status',[\App\Http\Controllers\AdminController::class,'status'])->whereNumber('id')->middleware('throttle:account-login');
                Route::get('/deliveries',[\App\Http\Controllers\AdminController::class,'deliveries']);
            });
            Route::put('/following/{id}/reminders',[\App\Http\Controllers\ReminderPreferencesController::class,'update'])->whereNumber('id')->middleware('throttle:60,1');
            Route::get('/following',[\App\Http\Controllers\FollowingController::class,'index']);
            Route::post('/following',[\App\Http\Controllers\FollowingController::class,'follow'])->middleware('throttle:60,1');
            Route::put('/primary-team',[\App\Http\Controllers\FollowingController::class,'primary'])->middleware('throttle:60,1');
            Route::delete('/following/{id}',[\App\Http\Controllers\FollowingController::class,'unfollow'])->whereNumber('id');
            Route::get('/refresh',[\App\Http\Controllers\RefreshController::class,'accountStatus']);
            Route::post('/refresh',[\App\Http\Controllers\RefreshController::class,'accountRefresh']);
            Route::post('/logout',[Accounts::class,'logout']);
            Route::post('/email/resend',[Accounts::class,'resend'])->middleware('throttle:account-mail');
            Route::get('/email/verify/{id}/{hash}',[Accounts::class,'verify'])->middleware('signed')->name('verification.verify');
            Route::patch('/profile',[Accounts::class,'update'])->middleware('throttle:account-mail');
            Route::put('/password',[Accounts::class,'password'])->middleware('throttle:account-login');
            Route::get('/sessions',[Accounts::class,'sessions']);
            Route::delete('/sessions',[Accounts::class,'closeOthers'])->middleware('throttle:account-login');
            Route::delete('/',[Accounts::class,'destroy'])->middleware('throttle:account-login');
        });
    });
});

Route::match(['get','post'],'emails/rappels/{user}/desactiver',[\App\Http\Controllers\ReminderPreferencesController::class,'unsubscribe'])
    ->whereNumber('user')->middleware(['signed','throttle:12,1'])->name('reminders.unsubscribe');
