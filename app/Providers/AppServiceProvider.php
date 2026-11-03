<?php
namespace App\Providers;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {
        URL::forceRootUrl(config('app.url'));
        RateLimiter::for('account-login',fn($r)=>[
            Limit::perMinute(5)->by(Str::lower((string)$r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
        RateLimiter::for('account-register',fn($r)=>Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('account-mail',fn($r)=>Limit::perMinute(3)->by($r->ip()));
        VerifyEmail::toMailUsing(fn($user,$url)=>(new MailMessage)->subject('Vérifiez votre adresse — Ligue 1 Live')->greeting('Bienvenue sur Ligue 1 Live !')->line('Confirmez votre adresse pour terminer la création de votre compte.')->action('Vérifier mon adresse',$url)->line('Ce lien expire dans une heure. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.'));
        ResetPassword::createUrlUsing(fn($user,$token)=>url('/mot-de-passe/reinitialiser').'?'.http_build_query(['token'=>$token,'email'=>$user->email]));
        ResetPassword::toMailUsing(fn($user,$token)=>(new MailMessage)->subject('Réinitialisez votre mot de passe — Ligue 1 Live')->greeting('Bonjour,')->line('Une demande de réinitialisation a été effectuée pour votre compte.')->action('Choisir un nouveau mot de passe',url('/mot-de-passe/reinitialiser').'?'.http_build_query(['token'=>$token,'email'=>$user->email]))->line('Ce lien expire dans une heure. Si vous n’avez rien demandé, ignorez cet e-mail.'));
    }
}
