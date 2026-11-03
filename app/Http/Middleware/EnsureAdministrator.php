<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureAdministrator {
    public function handle(Request $request,Closure $next) {
        $user=$request->user();
        abort_unless($user&&$user->active&&$user->role==='admin'&&$user->hasVerifiedEmail(),403,'Cet espace est réservé aux administrateurs dont l’adresse est vérifiée.');
        return $next($request);
    }
}
