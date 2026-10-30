<?php
namespace Tests\Feature;
use App\Football\ApiClient;
use App\Football\ApiError;
use App\Football\League;
use App\Football\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class FootballTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['football.provider'=>'highlightly','football.highlightly_key'=>'fake-private-key','football.api_football_key'=>'fake-other-key','football.season'=>2026,'football.daily_limit'=>90,'football.spacing_ms'=>0]);
        Cache::flush();Http::preventStrayRequests();$this->travelTo(now()->setDate(2026,10,30)->setTime(12,0));
    }
    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }
    private function matchRow(int $id): array
    {
        return ['id'=>$id,'date'=>'2026-10-31T19:00:00Z','round'=>'Regular Season - 9','league'=>['id'=>52695,'season'=>2026], 'state'=>['description'=>'Not started'], 'homeTeam'=>['id'=>1,'name'=>'Club A'],'awayTeam'=>['id'=>2,'name'=>'Club B']];
    }
    public function test_pagination_cache_and_private_headers_keep_the_vue_contract(): void
    {
        Http::fake(function($request) {
            $this->assertTrue($request->hasHeader('x-rapidapi-key','fake-private-key'));
            $this->assertStringNotContainsString('fake-private-key',$request->url());
            $offset=(int)$request['offset'];$count=$offset===0?100:1;
            return Http::response(['data'=>array_map(fn($i)=>$this->matchRow($i),range($offset+1,$offset+$count)), 'pagination'=>['totalCount'=>101]],200,['x-ratelimit-requests-remaining'=>'98']);
        });
        $this->getJson('/api/matches')->assertOk()->assertJsonCount(101,'matches')->assertJsonPath('matches.0.homeGoals',null)->assertJsonPath('matches.0.round',9)->assertJsonPath('mode','api')->assertJsonPath('budget.used',2)->assertDontSee('fake-private-key');
        $this->getJson('/api/matches')->assertOk();Http::assertSentCount(2);
        $this->assertSame(2,(new ApiClient('highlightly'))->budget()['used']);
    }
    public function test_pause_manual_and_budget_block_requests_before_sending(): void
    {
        Http::fake(['*'=>Http::response(['data'=>[]])]);
        Cache::forever('football:mode','pause');
        $this->getJson('/api/matches')->assertStatus(409);Http::assertNothingSent();
        Cache::forever('football:mode','manual');
        $this->getJson('/api/matches')->assertStatus(409);Http::assertNothingSent();
        $client=new ApiClient('highlightly');
        $client->request('test',[],1000,true);Http::assertSentCount(1);
        config(['football.daily_limit'=>1]);
        try { $client->request('another',[],1000,true);$this->fail('Quota non appliqué'); } catch(ApiError $e) { $this->assertSame(429,$e->getStatusCode()); }
        Http::assertSentCount(1);
    }
    public function test_stale_fallback_and_missing_data_are_not_fabricated(): void
    {
        Http::fakeSequence()->push(['data'=>['real-data']],200)->pushStatus(503);
        $client=new ApiClient('highlightly');$first=$client->request('test',[],1000);
        $this->travel(2)->seconds();
        $old=$client->request('test',[],1000);
        $this->assertTrue($old['stale']);$this->assertSame($first['fetchedAt'],$old['fetchedAt']);$this->assertSame($first['data'],$old['data']);
        Cache::forever('football:mode','pause');
        $this->assertTrue($client->request('test',[],1000)['stale']);Http::assertSentCount(2);
    }
    public function test_errors_and_wrong_competition_do_not_become_demo(): void
    {
        $row=$this->matchRow(1);$row['league']['id']=999;
        Http::fake(['*'=>Http::response(['data'=>[$row],'pagination'=>['totalCount'=>1]])]);
        $this->getJson('/api/matches')->assertStatus(502)->assertJsonStructure(['error'])->assertDontSee('fake-private-key');
        Http::fake(['*'=>Http::response(['errors'=>['token'=>'fake-private-key']])]);
        try { (new ApiClient('api-football'))->request('test',[],1000);$this->fail('Erreur fournisseur ignorée'); } catch(ApiError $e) { $this->assertStringNotContainsString('fake-private-key',$e->getMessage()); }
    }
    public function test_standings_shape_and_provider_budgets_are_separate(): void
    {
        $row=['position'=>1,'team'=>['id'=>1,'name'=>'Club A'],'points'=>3,'total'=>['scoredGoals'=>2,'receivedGoals'=>0,'games'=>1,'wins'=>1,'draws'=>0,'loses'=>0]];
        Http::fake(['soccer.highlightly.net/*'=>Http::response(['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>[$row]]]]),'v3.football.api-sports.io/*'=>Http::response(['response'=>[]])]);
        $this->getJson('/api/overview')->assertOk()->assertJsonPath('standings.0.points',3)->assertJsonPath('standings.0.difference',2)->assertJsonPath('teams.0.id',1);
        $this->getJson('/api/overview')->assertOk();Http::assertSentCount(1);
        (new ApiClient('api-football'))->request('test',[],1000);
        $this->assertSame(1,(new ApiClient('highlightly'))->budget()['used']);$this->assertSame(1,(new ApiClient('api-football'))->budget()['used']);
    }
    public function test_api_football_fixtures_and_overview_keep_the_same_contract(): void
    {
        config(['football.provider'=>'api-football']);
        $team=['id'=>1,'name'=>'A'];
        $fixture=['fixture'=>['id'=>1,'date'=>'2026-10-31T19:00:00Z','status'=>['short'=>'NS']],'teams'=>['home'=>$team,'away'=>['id'=>2,'name'=>'B']],'goals'=>['home'=>null,'away'=>null],'league'=>['round'=>'Regular Season - 9']];
        $row=['rank'=>1,'team'=>$team,'points'=>3,'goalsDiff'=>2,'all'=>['played'=>1,'win'=>1,'draw'=>0,'lose'=>0,'goals'=>['for'=>2,'against'=>0]]];
        Http::fake(['*/fixtures*'=>Http::response(['response'=>[$fixture]]),'*/leagues*'=>Http::response(['response'=>[['seasons'=>[['year'=>2026]]]]]),'*/standings*'=>Http::response(['response'=>[['league'=>['standings'=>[[$row]]]]]]),'*/teams*'=>Http::response(['response'=>[['team'=>$team,'venue'=>['name'=>'Stade A']]]])]);
        $this->getJson('/api/matches')->assertOk()->assertJsonPath('matches.0.homeGoals',null);
        $this->getJson('/api/overview')->assertOk()->assertJsonPath('teams.0.stadium','Stade A')->assertJsonPath('standings.0.points',3);
        Http::assertSentCount(4);
    }
    public function test_paris_days_dst_and_minimum_refresh_delays(): void
    {
        $this->assertSame(Schedule::timestamp('2026-10-25T23:00:00Z'),Schedule::nextDay(Schedule::timestamp('2026-10-25T00:30:00Z')));
        $match=League::highlightlyFixture($this->matchRow(1));$time=Schedule::timestamp('2026-10-31T19:00:00Z');
        $entry=['time'=>$time-60000,'data'=>[$match]];
        $this->assertTrue(Schedule::fixturesFresh($entry,$time));
        $this->assertFalse(Schedule::fixturesFresh($entry,$time+600000));
        $this->assertSame($entry['time']+600000,Schedule::nextFixtures($entry,$time));
        $this->assertTrue(Schedule::standingsFresh(['time'=>$time,'signature'=>'old'],'new',$time+1000));
        $this->assertFalse(Schedule::standingsFresh(['time'=>$time,'signature'=>'old'],'new',$time+3600000));
    }
    public function test_status_never_calls_upstream_or_exposes_credentials(): void
    {
        $this->getJson('/api/status')->assertOk()->assertJsonPath('source','highlightly')->assertDontSee('fake-private-key')->assertDontSee('fake-other-key');Http::assertNothingSent();
        $this->getJson('/api/unknown')->assertNotFound();
    }
    public function test_control_validation_origin_and_request_modes(): void
    {
        $this->postJson('/api/control',['mode'=>'pause'])->assertOk()->assertJsonPath('refreshMode','pause')->assertJsonPath('nextRefreshAt',null);
        $this->getJson('/api/matches')->assertStatus(409);
        $this->postJson('/api/control',['mode'=>'invalid'])->assertStatus(400);
        $this->postJson('/api/control',['mode'=>'auto'],['Origin'=>'https://evil.example'])->assertStatus(403);
        $this->postJson('/api/control',['mode'=>'auto'],['Sec-Fetch-Site'=>'cross-site'])->assertStatus(403);
        $this->getJson('/api/status')->assertJsonPath('refreshMode','pause');Http::assertNothingSent();
    }
    public function test_file_cache_and_quota_survive_a_new_client_and_connection(): void
    {
        $directory=sys_get_temp_dir().'/l1-php-cache-'.bin2hex(random_bytes(8));
        config(['cache.default'=>'file','cache.stores.file.path'=>$directory.'/data','cache.stores.file.lock_path'=>$directory.'/locks']);
        Cache::purge('file');
        try {
            Http::fake(['*'=>Http::response(['data'=>['persisted']])]);
            $first=(new ApiClient('highlightly'))->request('test',[],1000);
            Cache::purge('file');
            $client=new ApiClient('highlightly');
            $this->assertSame($first,$client->request('test',[],1000));
            $this->assertSame(1,$client->budget()['used']);Http::assertSentCount(1);
            $this->postJson('/api/control',['mode'=>'pause'])->assertOk();
            Cache::purge('file');$this->assertSame('pause',ApiClient::mode());
        } finally { \Illuminate\Support\Facades\File::deleteDirectory($directory); Cache::purge('file'); }
    }
    public function test_failed_calls_count_against_quota_and_utc_reset_is_shared(): void
    {
        Http::fake(['*'=>Http::response(['errors'=>['quota'=>'limited']],429)]);
        $client=new ApiClient('highlightly');
        try { $client->request('test',[],1000); } catch(ApiError $e) { $this->assertSame(429,$e->getStatusCode()); }
        $this->assertSame(1,$client->budget()['used']);
        $this->travel(1)->days();$this->assertSame(0,$client->budget()['used']);
        $this->assertSame(90,$client->budget()['remaining']);
    }
}
