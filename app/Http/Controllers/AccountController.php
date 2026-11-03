<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Illuminate\Support\Facades\Log;
class AccountController extends Controller
{
    private function userData(?User $user): ?array {
        return $user ? $user->only(['id','email','email_verified_at','role','created_at']) : null;
    }
    private function reply(Request $request, string $message='') {
        return response()->json(['enabled'=>true,'user'=>$this->userData($request->user()),'csrfToken'=>csrf_token(),'message'=>$message,'following'=>\App\Football\Following::snapshot($request->user())])->header('Cache-Control','no-store');
    }
    public function session(Request $request) {
        if(!config('accounts.enabled')) return response()->json(['enabled'=>false,'user'=>null])->header('Cache-Control','no-store');
        if($request->user() && !$request->user()->active) { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); }
        return $this->reply($request);
    }
    private function passwordRules(): array { return ['required','string',PasswordRule::min(12),'max:72','confirmed',fn($attribute,$value,$fail)=>strlen($value)>72?$fail('Ce mot de passe est trop long. Raccourcissez-le.'):null]; }
    private function email(Request $request): void {
        if(is_string($request->input('email'))) $request->merge(['email'=>Str::lower(trim($request->input('email')))]);
    }
    public function register(Request $request) {
        abort_if($request->user(),409,'Vous êtes déjà connecté.'); $this->email($request);
        $data=$request->validate(['email'=>['required','email','max:254','unique:users,email'],'password'=>$this->passwordRules()]);
        $user=User::create($data); Auth::login($user); $request->session()->regenerate();
        $request->session()->put('password_hash_web',$user->password);
        try { event(new Registered($user)); }
        catch(TransportExceptionInterface $e) { Log::warning('account_mail_failed',['user_id'=>$user->id,'type'=>'verification']);return $this->reply($request,'Compte créé, mais l’e-mail n’a pas pu être envoyé. Vous pouvez renvoyer le lien depuis votre compte.'); }
        return $this->reply($request,'Compte créé. Vérifiez votre adresse grâce au lien reçu par e-mail.');
    }
    public function login(Request $request) {
        $this->email($request); $data=$request->validate(['email'=>['required','email'],'password'=>['required','string','max:72']]);
        if(!Auth::attempt([...$data,'active'=>true])) throw ValidationException::withMessages(['email'=>'Adresse ou mot de passe incorrect.']);
        $request->session()->regenerate(); $request->session()->put('password_hash_web',$request->user()->password);
        return $this->reply($request,'Vous êtes connecté.');
    }
    public function logout(Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return $this->reply($request,'Vous êtes déconnecté.');
    }
    public function resend(Request $request) {
        try { if(!$request->user()->hasVerifiedEmail())$request->user()->sendEmailVerificationNotification(); }
        catch(TransportExceptionInterface $e) { Log::warning('account_mail_failed',['user_id'=>$request->user()->id,'type'=>'verification']);return response()->json(['message'=>'L’e-mail n’a pas pu être envoyé. Réessayez dans un instant.'],503); }
        return $this->reply($request,'Si nécessaire, un nouveau lien de vérification vous a été envoyé.');
    }
    public function verify(EmailVerificationRequest $request) {
        $request->fulfill(); return redirect('/mon-compte?adresse=verifiee');
    }
    public function forgot(Request $request) {
        $this->email($request); $request->validate(['email'=>['required','email']]);
        try { Password::sendResetLink($request->only('email')); }
        catch(TransportExceptionInterface $e) { Log::warning('account_mail_failed',['type'=>'password_reset']); }
        return response()->json(['message'=>'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.']);
    }
    public function reset(Request $request) {
        $this->email($request); $request->validate(['email'=>['required','email'],'token'=>['required','string'],'password'=>$this->passwordRules()]);
        $status=Password::reset($request->only('email','password','password_confirmation','token'),function(User $user,string $password) {
            DB::transaction(function() use($user,$password) {
                $user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();
                DB::table('sessions')->where('user_id',$user->id)->delete();
            });
            event(new PasswordReset($user));
        });
        if($status!==Password::PASSWORD_RESET) throw ValidationException::withMessages(['email'=>'Ce lien est invalide ou a expiré. Demandez un nouveau lien.']);
        return response()->json(['message'=>'Mot de passe modifié. Vous pouvez vous connecter.']);
    }
    public function update(Request $request) {
        $this->email($request); $user=$request->user();
        $data=$request->validate(['email'=>['required','email','max:254',Rule::unique('users')->ignore($user->id)],'current_password'=>['required','current_password']]);
        if($data['email']!==$user->email) {
            $oldEmail=$user->email; $user->forceFill(['email'=>$data['email'],'email_verified_at'=>null])->save();
            DB::table('password_reset_tokens')->where('email',$oldEmail)->delete();
            try { $user->sendEmailVerificationNotification(); }
            catch(TransportExceptionInterface $e) { Log::warning('account_mail_failed',['user_id'=>$user->id,'type'=>'verification']);return $this->reply($request,'Adresse enregistrée, mais l’e-mail n’a pas pu être envoyé. Vous pouvez renvoyer le lien depuis votre compte.'); }
        }
        return $this->reply($request,'Profil enregistré. Vérifiez votre nouvelle adresse si elle a changé.');
    }
    public function password(Request $request) {
        $request->validate(['current_password'=>['required','current_password'],'password'=>$this->passwordRules()]);
        $user=$request->user(); $user->forceFill(['password'=>$request->input('password'),'remember_token'=>Str::random(60)])->save();
        $request->session()->put('password_hash_web',$user->password); $request->session()->regenerate();
        DB::table('sessions')->where('user_id',$user->id)->where('id','!=',$request->session()->getId())->delete();
        DB::table('password_reset_tokens')->where('email',$user->email)->delete();
        return $this->reply($request,'Mot de passe modifié. Les autres sessions ont été fermées.');
    }
    public function sessions(Request $request) {
        abort_unless(config('session.driver')==='database',503,'La liste des sessions nécessite le stockage en base.');
        return response()->json(['sessions'=>DB::table('sessions')->where('user_id',$request->user()->id)->orderByDesc('last_activity')->get(['id','last_activity'])->map(fn($session)=>['current'=>$session->id===$request->session()->getId(),'lastActiveAt'=>$session->last_activity*1000])])->header('Cache-Control','no-store');
    }
    public function closeOthers(Request $request) {
        $request->validate(['current_password'=>['required','current_password']]);
        Auth::logoutOtherDevices($request->input('current_password'));
        DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->delete();
        return $this->reply($request,'Les autres sessions ont été fermées.');
    }
    public function destroy(Request $request) {
        $request->validate(['current_password'=>['required','current_password']]); $user=$request->user();
        DB::transaction(function() use($user) {
            Auth::logout();
            DB::table('sessions')->where('user_id',$user->id)->delete();
            DB::table('password_reset_tokens')->where('email',$user->email)->delete(); $user->delete();
        });
        $request->session()->invalidate(); $request->session()->regenerateToken();
        return $this->reply($request,'Votre compte a été supprimé.');
    }
}
