<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureAccountsEnabled {
    public function handle(Request $request, Closure $next) {
        abort_unless(config('accounts.enabled'),503,'Les comptes ne sont pas encore ouverts.');
        if($request->user() && !$request->user()->active) {
            auth()->logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
            abort(403,'Ce compte est désactivé.');
        }
        return $next($request);
    }
}
