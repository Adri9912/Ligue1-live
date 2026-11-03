<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Team;
use App\Models\FootballMatch;
use App\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
class AdminTest extends TestCase {
    use RefreshDatabase;
    protected function beforeRefreshingDatabase():void {
        if(!app()->environment('testing')||config('database.default')!=='mysql'||config('database.connections.mysql.url')||config('database.connections.mysql.database')!=='ligue1_live_test')throw new \LogicException('Base dédiée obligatoire.');
    }
    private const PASS='Une phrase administrateur 2026!';
    protected function setUp():void {
        parent::setUp();config(['accounts.enabled'=>true,'football.highlightly_key'=>'dummy-secret-highlightly-key','football.api_football_key'=>'dummy-secret-football-key']);
        Cache::flush();Notification::fake();Http::preventStrayRequests();
    }
    private function member(string $email='user@example.test',bool $admin=false):User {
        $user=User::create(['email'=>$email,'password'=>self::PASS]);$user->markEmailAsVerified();
        if($admin)$user->forceFill(['role'=>'admin'])->save();return $user;
    }
    public function test_guests_regular_unverified_and_disabled_users_cannot_access_admin():void {
        foreach(['/api/account/admin','/api/account/admin/users','/api/account/admin/deliveries'] as $url)$this->getJson($url)->assertUnauthorized();
        $user=$this->member();$this->actingAs($user)->getJson('/api/account/admin')->assertForbidden();
        $this->patchJson('/api/account/admin/users/'.$user->id.'/status',['active'=>false,'current_password'=>self::PASS])->assertForbidden();
        $user->forceFill(['role'=>'admin','email_verified_at'=>null])->save();$this->getJson('/api/account/admin')->assertForbidden();
        $user->forceFill(['email_verified_at'=>now(),'active'=>false])->save();$this->getJson('/api/account/admin')->assertForbidden();
    }
    public function test_overview_is_private_and_reads_budgets_without_football_calls():void {
        $admin=$this->member('admin@example.test',true);$this->member();
        config(['reminders.enabled'=>true,'football.refresh_mode'=>'auto']);
        Cache::forever('football:quota:highlightly',['day'=>now()->utc()->format('Y-m-d'),'used'=>12]);
        $this->actingAs($admin)->getJson('/api/account/admin')->assertOk()->assertHeader('Cache-Control','no-store, private')->assertJsonPath('stats.users',2)->assertJsonPath('stats.active',2)->assertJsonPath('budgets.0.budget.used',12)->assertJsonPath('reminderActivity.tasks.0.state','never')->assertDontSee('dummy-secret')->assertDontSee('password');
        Http::assertNothingSent();
    }
    public function test_users_search_is_literal_paginated_and_does_not_expose_session_or_password():void {
        $admin=$this->member('admin@example.test',true);$this->member('per_cent@example.test');$this->member('percent@example.test');
        $r=$this->actingAs($admin)->getJson('/api/account/admin/users?q=_')->assertOk()->assertJsonPath('users.total',1)->assertJsonPath('users.data.0.email','per_cent@example.test');
        $r->assertJsonMissingPath('users.data.0.password')->assertJsonMissingPath('users.data.0.remember_token');
        $this->getJson('/api/account/admin/users?page=-1')->assertUnprocessable();
        for($i=0;$i<19;$i++)DB::table('users')->insert(['email'=>"page-$i@example.test",'password'=>$admin->password,'created_at'=>now(),'updated_at'=>now()]);
        $this->getJson('/api/account/admin/users?page=1')->assertOk()->assertJsonCount(20,'users.data')->assertJsonPath('users.last_page',2);
        $this->getJson('/api/account/admin/users?page=2')->assertOk()->assertJsonCount(2,'users.data');
    }
    public function test_status_change_requires_password_and_preserves_other_users_and_favorites():void {
        $admin=$this->member('admin@example.test',true);$user=$this->member();$other=$this->member('other@example.test');
        $team=Team::create(['identity_key'=>'psg','name'=>'PSG','full_name'=>'Paris SG','code'=>'PSG','highlightly_id'=>1,'last_seen_season'=>2026]);$user->followedTeams()->attach($team->id);
        foreach([$user,$other] as $u)DB::table('sessions')->insert(['id'=>'session-'.$u->id,'user_id'=>$u->id,'payload'=>'','last_activity'=>time()]);
        DB::table('password_reset_tokens')->insert(['email'=>$user->email,'token'=>'dummy-reset','created_at'=>now()]);
        $url='/api/account/admin/users/'.$user->id.'/status';
        $this->actingAs($admin)->patchJson($url,['active'=>false,'current_password'=>'wrong'])->assertUnprocessable();$this->assertTrue($user->fresh()->active);
        $this->patchJson($url,['active'=>false,'current_password'=>self::PASS,'role'=>'admin'])->assertOk();
        $this->assertFalse($user->fresh()->active);$this->assertSame('user',$user->fresh()->role);$this->assertSame(1,$user->followedTeams()->count());
        $this->assertDatabaseMissing('sessions',['user_id'=>$user->id]);$this->assertDatabaseHas('sessions',['user_id'=>$other->id]);$this->assertDatabaseMissing('password_reset_tokens',['email'=>$user->email]);
        $this->assertTrue($other->fresh()->active);$this->patchJson($url,['active'=>true,'current_password'=>self::PASS])->assertOk();$this->assertTrue($user->fresh()->active);
    }
    public function test_admin_accounts_are_protected_from_status_changes():void {
        $admin=$this->member('admin@example.test',true);$other=$this->member('other@example.test',true);
        foreach([$admin,$other] as $u)$this->actingAs($admin)->patchJson('/api/account/admin/users/'.$u->id.'/status',['active'=>false,'current_password'=>self::PASS])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->active);$this->assertTrue($other->fresh()->active);
    }
    public function test_delivery_history_filters_safe_fields_and_disabling_cancels_pending():void {
        $admin=$this->member('admin@example.test',true);$user=$this->member();
        $home=Team::create(['identity_key'=>'home','name'=>'Home','full_name'=>'Home','code'=>'HOM','last_seen_season'=>2026]);$away=Team::create(['identity_key'=>'away','name'=>'Away','full_name'=>'Away','code'=>'AWA','last_seen_season'=>2026]);
        $game=FootballMatch::create(['provider'=>'highlightly','external_id'=>4,'season'=>2026,'home_team_id'=>$home->id,'away_team_id'=>$away->id,'kickoff_at'=>now()->addHours(2),'status'=>'NS','fixture'=>['home'=>['name'=>'PSG'],'away'=>['name'=>'Lyon']],'fetched_at'=>now()]);
        $delivery=NotificationDelivery::create(['user_id'=>$user->id,'match_id'=>$game->id,'expected_kickoff_at'=>$game->kickoff_at,'last_error'=>'dummy-private-error']);
        $this->actingAs($admin)->getJson('/api/account/admin/deliveries?status=pending')->assertOk()->assertJsonPath('deliveries.data.0.match','PSG – Lyon')->assertDontSee('dummy-private-error')->assertJsonMissingPath('deliveries.data.0.user.password');
        $this->getJson('/api/account/admin/deliveries?status=unknown')->assertUnprocessable();
        $this->patchJson('/api/account/admin/users/'.$user->id.'/status',['active'=>false,'current_password'=>self::PASS])->assertOk();$this->assertSame('cancelled',$delivery->fresh()->status);
        $this->getJson('/api/account/admin/deliveries?status=pending')->assertJsonPath('deliveries.total',0);
    }
    public function test_promotion_is_only_a_private_command_and_requires_verified_active_account_and_confirmation():void {
        $user=$this->member();$user->forceFill(['email_verified_at'=>null])->save();
        $this->artisan('account:make-admin',['email'=>$user->email])->assertExitCode(1);$this->assertSame('user',$user->fresh()->role);
        $user->markEmailAsVerified();$this->artisan('account:make-admin',['email'=>$user->email])->expectsConfirmation('Accorder les droits administrateur à ce compte ?','no')->assertExitCode(1);
        $this->assertSame('user',$user->fresh()->role);
        $this->artisan('account:make-admin',['email'=>$user->email])->expectsConfirmation('Accorder les droits administrateur à ce compte ?','yes')->assertExitCode(0);$this->assertSame('admin',$user->fresh()->role);
    }
    public function test_disabled_accounts_feature_closes_administration_too():void {
        $admin=$this->member('admin@example.test',true);config(['accounts.enabled'=>false]);
        $this->actingAs($admin)->getJson('/api/account/admin')->assertStatus(503);
    }
}
