<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
class AccountsTest extends TestCase
{
    use RefreshDatabase;
    private const PASS='Une longue phrase 2026!';
    protected function setUp(): void {
        parent::setUp();
        // Refuser tout rafraîchissement des tables hors de la base de tests dédiée.
        $this->assertSame('ligue1_live_test',config('database.connections.mysql.database'));
        config(['accounts.enabled'=>true]); Cache::flush(); Notification::fake();
    }
    protected function beforeRefreshingDatabase(): void {
        if(!app()->environment('testing') || config('database.default')!=='mysql' || config('database.connections.mysql.url') || config('database.connections.mysql.database')!=='ligue1_live_test') throw new \LogicException('Tests refusés hors de la base dédiée.');
    }
    private function member(string $email='membre@example.test'): User { return User::create(['email'=>$email,'password'=>self::PASS]); }
    public function test_registration_has_only_user_role_hashed_password_and_verification_mail(): void {
        $this->postJson('/api/account/register',['email'=>' MEMBRE@example.test ','password'=>self::PASS,'password_confirmation'=>self::PASS,'role'=>'admin','active'=>false])->assertOk()->assertJsonPath('user.email','membre@example.test')->assertJsonPath('user.role','user')->assertJsonPath('user.email_verified_at',null)->assertJsonMissingPath('user.password')->assertDontSee(self::PASS);
        $user=User::first();$this->assertTrue($user->active);$this->assertTrue(Hash::check(self::PASS,$user->password));
        Notification::assertSentTo($user,VerifyEmail::class);
        $this->getJson('/api/account/session')->assertOk()->assertJsonPath('user.id',$user->id);
    }
    public function test_registration_and_password_validation_do_not_store_invalid_users(): void {
        $this->postJson('/api/account/register',['email'=>'bad','password'=>'short','password_confirmation'=>'other'])->assertUnprocessable();
        $this->assertDatabaseCount('users',0);
        $this->postJson('/api/account/register',['email'=>'a@example.test','password'=>str_repeat('é',40),'password_confirmation'=>str_repeat('é',40)])->assertUnprocessable();
        $this->assertDatabaseCount('users',0);
    }
    public function test_login_logout_and_inactive_account(): void {
        $user=$this->member();
        $this->postJson('/api/account/login',['email'=>$user->email,'password'=>'bad'])->assertUnprocessable();
        $this->postJson('/api/account/login',['email'=>$user->email,'password'=>self::PASS])->assertOk()->assertJsonPath('user.id',$user->id);
        $this->postJson('/api/account/logout')->assertOk()->assertJsonPath('user',null);$this->assertGuest();
        $user->forceFill(['active'=>false])->save();
        $this->postJson('/api/account/login',['email'=>$user->email,'password'=>self::PASS])->assertUnprocessable();
    }
    public function test_login_is_throttled_and_guests_cannot_read_or_change_accounts(): void {
        $this->getJson('/api/account/sessions')->assertUnauthorized();
        $this->patchJson('/api/account/profile',['email'=>'x@example.test'])->assertUnauthorized();
        for($i=0;$i<5;$i++)$this->postJson('/api/account/login',['email'=>'x@example.test','password'=>'wrong'])->assertUnprocessable();
        $this->postJson('/api/account/login',['email'=>'x@example.test','password'=>'wrong'])->assertTooManyRequests();
    }
    public function test_verification_requires_correct_signature_owner_email_and_unexpired_link(): void {
        $a=$this->member();$b=$this->member('autre@example.test');
        $url=URL::temporarySignedRoute('verification.verify',now()->addHour(),['id'=>$a->id,'hash'=>sha1($a->email)]);
        $this->actingAs($b)->getJson($url)->assertForbidden();
        $this->actingAs($a)->withSession(['password_hash_web'=>$a->password])->getJson($url.'&tampered=1')->assertForbidden();
        $expired=URL::temporarySignedRoute('verification.verify',now()->subMinute(),['id'=>$a->id,'hash'=>sha1($a->email)]);
        $this->getJson($expired)->assertForbidden();
        $this->get($url)->assertRedirect('/mon-compte?adresse=verifiee');$this->assertTrue($a->fresh()->hasVerifiedEmail());
    }
    public function test_forgot_password_is_generic_and_reset_token_is_single_use(): void {
        $user=$this->member();
        $unknown=$this->postJson('/api/account/forgot-password',['email'=>'inconnu@example.test'])->assertOk()->json('message');
        $this->postJson('/api/account/forgot-password',['email'=>$user->email])->assertOk()->assertJsonPath('message',$unknown);
        $token=null;Notification::assertSentTo($user,ResetPassword::class,function($n) use (&$token) { $token=$n->token;return true; });
        $new='Une autre longue phrase 2026!';
        DB::table('sessions')->insert(['id'=>'old-session','user_id'=>$user->id,'payload'=>'','last_activity'=>time()]);
        $data=['email'=>$user->email,'token'=>$token,'password'=>$new,'password_confirmation'=>$new];
        $this->postJson('/api/account/reset-password',[...$data,'token'=>'wrong'])->assertUnprocessable();
        $this->postJson('/api/account/reset-password',$data)->assertOk();$this->assertTrue(Hash::check($new,$user->fresh()->password));$this->assertDatabaseMissing('sessions',['id'=>'old-session']);
        $this->postJson('/api/account/reset-password',$data)->assertUnprocessable();
    }
    public function test_expired_password_reset_token_is_refused(): void {
        $user=$this->member();$token=Password::createToken($user);$this->travel(61)->minutes();
        $this->postJson('/api/account/reset-password',['email'=>$user->email,'token'=>$token,'password'=>self::PASS,'password_confirmation'=>self::PASS])->assertUnprocessable();
    }
    public function test_profile_changes_require_password_do_not_touch_other_user_and_reverify_email(): void {
        $a=$this->member();$b=$this->member('autre@example.test');$a->markEmailAsVerified();
        $this->actingAs($a)->patchJson('/api/account/profile',['email'=>'nouveau@example.test','current_password'=>'bad','id'=>$b->id])->assertUnprocessable();
        $this->patchJson('/api/account/profile',['email'=>'nouveau@example.test','current_password'=>self::PASS,'id'=>$b->id,'role'=>'admin'])->assertOk()->assertJsonPath('user.email_verified_at',null)->assertJsonPath('user.role','user');
        $this->assertSame('autre@example.test',$b->fresh()->email);Notification::assertSentTo($a,VerifyEmail::class);
    }
    public function test_password_change_and_delete_only_affect_current_user(): void {
        $a=$this->member();$b=$this->member('autre@example.test');
        foreach([$a,$b] as $u)DB::table('sessions')->insert(['id'=>'session-'.$u->id,'user_id'=>$u->id,'payload'=>'','last_activity'=>time()]);
        $new='Ma nouvelle phrase 2026!';
        $this->actingAs($a)->putJson('/api/account/password',['current_password'=>self::PASS,'password'=>$new,'password_confirmation'=>$new])->assertOk();
        $this->assertTrue(Hash::check($new,$a->fresh()->password));$this->assertDatabaseMissing('sessions',['user_id'=>$a->id]);$this->assertDatabaseHas('sessions',['user_id'=>$b->id]);
        $this->deleteJson('/api/account',['current_password'=>'bad'])->assertUnprocessable();
        $this->deleteJson('/api/account',['current_password'=>$new,'id'=>$b->id])->assertOk()->assertJsonPath('user',null);
        $this->assertDatabaseMissing('users',['id'=>$a->id]);$this->assertDatabaseHas('users',['id'=>$b->id]);
    }
    public function test_disabled_feature_does_not_allow_signups(): void {
        config(['accounts.enabled'=>false]);
        $this->getJson('/api/account/session')->assertOk()->assertExactJson(['enabled'=>false,'user'=>null]);
        $this->postJson('/api/account/register',['email'=>'a@example.test','password'=>self::PASS,'password_confirmation'=>self::PASS])->assertServiceUnavailable();
        $this->assertDatabaseCount('users',0);
    }
    public function test_smtp_failure_does_not_lose_the_new_account_or_expose_diagnostics():void {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Registered::class,fn()=>throw new \Symfony\Component\Mailer\Exception\TransportException('private-smtp-diagnostic'));
        \Illuminate\Support\Facades\Log::spy();
        $this->postJson('/api/account/register',['email'=>'smtp-test@example.test','password'=>self::PASS,'password_confirmation'=>self::PASS])->assertOk()->assertJsonPath('user.email','smtp-test@example.test')->assertDontSee('private-smtp-diagnostic');
        $this->assertDatabaseCount('users',1);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->with('account_mail_failed',\Mockery::on(fn($context)=>isset($context['user_id'])&&$context['type']==='verification'&&count($context)===2))->once();
    }
    public function test_reset_smtp_failure_keeps_the_same_generic_response():void {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('private-smtp-diagnostic'));
        \Illuminate\Support\Facades\Log::spy();
        $this->postJson('/api/account/forgot-password',['email'=>'smtp-test@example.test'])->assertOk()->assertDontSee('private-smtp-diagnostic')->assertJsonStructure(['message']);
    }
    public function test_csrf_is_enforced_outside_test_bypass(): void {
        $this->app['env']='local';
        $this->postJson('/api/account/login',['email'=>'a@example.test','password'=>self::PASS])->assertStatus(419);
    }
}
