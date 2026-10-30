<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class ManualRefreshTest extends TestCase
{
    private const VISITOR='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    protected function setUp(): void {
        parent::setUp();$this->withCredentials();
        config(['football.provider'=>'highlightly','football.highlightly_key'=>'fake-private-key','football.api_football_key'=>'','football.refresh_mode'=>'auto','football.season'=>2026,'football.spacing_ms'=>0]);
        Cache::flush();Http::preventStrayRequests();
        $this->travelTo(now()->utc()->setDate(2026,10,30)->setTime(12,0));
        $team=['id'=>1,'name'=>'A'];
        $match=['id'=>10,'date'=>'2026-10-31T19:00:00Z','round'=>'Regular Season - 9','league'=>['id'=>52695,'season'=>2026],'state'=>['description'=>'Not started'],'homeTeam'=>$team,'awayTeam'=>['id'=>2,'name'=>'B']];
        $row=['position'=>1,'team'=>$team,'points'=>3,'total'=>['scoredGoals'=>2,'receivedGoals'=>0,'games'=>1,'wins'=>1,'draws'=>0,'loses'=>0]];
        Http::fake(['*/matches*'=>Http::response(['data'=>[$match],'pagination'=>['totalCount'=>1]]),'*/standings*'=>Http::response(['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>[$row]]]])]);
    }
    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }
    private function key(): string { return 'football:manual-clicks:2026-10-30:'.hash('sha256',self::VISITOR); }
    private function click() { return $this->withCookie('l1-refresh-visitor',self::VISITOR)->postJson('/api/refresh',[],['Origin'=>'http://localhost']); }
    public function test_counter_uses_a_private_encrypted_cookie_without_calling_the_provider(): void {
        $response=$this->getJson('/api/refresh')->assertOk()->assertJsonPath('manualRefresh.remaining',10)->assertCookie('l1-refresh-visitor');
        $cookie=$response->getCookie('l1-refresh-visitor',false);
        $this->assertTrue($cookie->isHttpOnly());$this->assertSame('lax',$cookie->getSameSite());
        $this->assertDoesNotMatchRegularExpression('/^[a-f0-9]{64}$/',$cookie->getValue());
        $response->assertDontSee('fake-private-key');Http::assertNothingSent();
    }
    public function test_tenth_click_is_accepted_but_eleventh_cannot_call_the_api(): void {
        Cache::put($this->key(),9,now()->addDay());
        $this->click()->assertOk()->assertJsonPath('manualRefresh.remaining',0)->assertJsonCount(1,'fixtures.matches')->assertJsonPath('refreshMode','auto')->assertDontSee('fake-private-key');
        $this->click()->assertStatus(429);
        $this->assertSame(10,Cache::get($this->key()));Http::assertSentCount(2);
        $this->getJson('/api/status')->assertJsonPath('refreshMode','auto')->assertJsonPath('budget.used',2);
        $this->withCookie('l1-refresh-visitor',self::VISITOR)->getJson('/api/refresh')->assertJsonPath('manualRefresh.remaining',0);
        $this->withCookie('l1-refresh-visitor',str_repeat('b',64))->getJson('/api/refresh')->assertJsonPath('manualRefresh.remaining',10);
        $this->travel(1)->days();
        $this->withCookie('l1-refresh-visitor',self::VISITOR)->getJson('/api/refresh')->assertJsonPath('manualRefresh.remaining',10);
    }
    public function test_repeated_clicks_share_the_cache_and_the_counter_survives_new_requests(): void {
        $this->click()->assertOk()->assertJsonPath('manualRefresh.remaining',9);
        $this->click()->assertOk()->assertJsonPath('manualRefresh.remaining',8);
        $this->withCookie('l1-refresh-visitor',self::VISITOR)->getJson('/api/refresh')->assertJsonPath('manualRefresh.remaining',8);
        Http::assertSentCount(2);
    }
    public function test_foreign_origins_forged_cookies_and_pause_cannot_spend_the_allowance(): void {
        $this->withUnencryptedCookie('l1-refresh-visitor',self::VISITOR)->postJson('/api/refresh',[],['Origin'=>'http://localhost'])->assertForbidden();
        $this->unencryptedCookies=[];
        $this->withCookie('l1-refresh-visitor',self::VISITOR)->postJson('/api/refresh',[],['Origin'=>'https://evil.example'])->assertForbidden();
        $this->withCookie('l1-refresh-visitor',self::VISITOR)->postJson('/api/refresh')->assertForbidden();
        config(['football.refresh_mode'=>'pause']);
        $this->click()->assertStatus(409);
        $this->assertNull(Cache::get($this->key()));Http::assertNothingSent();
    }
    public function test_manual_clicks_cannot_bypass_the_global_provider_budget(): void {
        config(['football.daily_limit'=>1]);
        $this->click()->assertStatus(429);
        $this->assertSame(1,Cache::get($this->key()));
        $this->getJson('/api/status')->assertJsonPath('budget.used',1)->assertJsonPath('budget.remaining',0)->assertJsonPath('refreshMode','auto');
        Http::assertSentCount(1);
    }
}
