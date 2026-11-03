<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
class AccountRefreshTest extends TestCase {
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void {
        if(!app()->environment('testing')||config('database.default')!=='mysql'||config('database.connections.mysql.url')||config('database.connections.mysql.database')!=='ligue1_live_test')throw new \LogicException('Base de test dédiée obligatoire.');
    }
    protected function setUp(): void {
        parent::setUp();config(['accounts.enabled'=>true,'football.provider'=>'highlightly','football.highlightly_key'=>'fake','football.refresh_mode'=>'auto','football.spacing_ms'=>0,'football.season'=>2026]);Cache::flush();Http::preventStrayRequests();
        $this->travelTo(now()->utc()->setDate(2026,11,2)->setTime(12,0));
        $team=['id'=>1,'name'=>'A'];$m=['id'=>10,'date'=>'2026-11-03T19:00:00Z','round'=>'9','league'=>['id'=>52695,'season'=>2026],'state'=>['description'=>'Not started'],'homeTeam'=>$team,'awayTeam'=>['id'=>2,'name'=>'B']];
        $row=['position'=>1,'team'=>$team,'points'=>3,'total'=>['games'=>1,'scoredGoals'=>2,'receivedGoals'=>0,'wins'=>1,'draws'=>0,'loses'=>0]];
        Http::fake(['*/matches*'=>Http::response(['data'=>[$m],'pagination'=>['totalCount'=>1]]),'*/standings*'=>Http::response(['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>[$row]]]])]);
    }
    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }
    private function user(string $email='a@example.test'):User { return User::create(['email'=>$email,'password'=>'Une phrase de test 2026!']); }
    private function click() { return $this->postJson('/api/account/refresh',[],['Origin'=>'http://localhost']); }
    public function test_guests_and_old_cookie_endpoint_cannot_bypass_account_limit():void {
        $this->getJson('/api/refresh')->assertJsonPath('manualRefresh',null);
        $this->postJson('/api/refresh',[],['Origin'=>'http://localhost'])->assertUnauthorized();
        $this->getJson('/api/account/refresh')->assertUnauthorized();$this->click()->assertUnauthorized();
        Http::assertNothingSent();$this->assertDatabaseCount('manual_refresh_counts',0);
    }
    public function test_clicks_belong_to_account_not_browser_cookie_and_cache_is_shared():void {
        $a=$this->user();$this->actingAs($a)->getJson('/api/account/refresh')->assertJsonPath('manualRefresh.remaining',10);
        $this->click()->assertOk()->assertJsonPath('manualRefresh.remaining',9)->assertJsonPath('manualRefresh.scope','user');
        $this->withCookie('l1-refresh-visitor','different-browser')->click()->assertOk()->assertJsonPath('manualRefresh.remaining',8);
        $this->getJson('/api/account/refresh')->assertJsonPath('manualRefresh.remaining',8);Http::assertSentCount(2);
        $b=$this->user('b@example.test');$this->actingAs($b)->withSession(['password_hash_web'=>$b->password])->getJson('/api/account/refresh')->assertJsonPath('manualRefresh.remaining',10);
    }
    public function test_tenth_click_accepted_eleventh_refused_and_utc_reset():void {
        $a=$this->user();DB::table('manual_refresh_counts')->insert(['user_id'=>$a->id,'day'=>'2026-11-02','used'=>9]);
        $this->actingAs($a)->click()->assertOk()->assertJsonPath('manualRefresh.remaining',0);
        $this->click()->assertTooManyRequests();Http::assertSentCount(2);
        $this->travel(1)->days();$this->getJson('/api/account/refresh')->assertJsonPath('manualRefresh.remaining',10);
    }
    public function test_pause_foreign_origin_and_global_budget_still_apply():void {
        $a=$this->user();$this->actingAs($a)->postJson('/api/account/refresh',[],['Origin'=>'https://evil.example'])->assertForbidden();
        config(['football.refresh_mode'=>'pause']);$this->click()->assertStatus(409);$this->assertDatabaseCount('manual_refresh_counts',0);Http::assertNothingSent();
        config(['football.refresh_mode'=>'auto','football.daily_limit'=>1]);$this->click()->assertTooManyRequests();
        $this->assertDatabaseHas('manual_refresh_counts',['user_id'=>$a->id,'used'=>1]);Http::assertSentCount(1);
    }
}
