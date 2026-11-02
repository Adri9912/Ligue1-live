<?php
namespace Tests\Feature;
use App\Football\Normalize;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class MatchLineupsTest extends TestCase
{
    private array $fixture;
    protected function setUp(): void {
        parent::setUp();
        config(['football.provider'=>'highlightly','football.highlightly_key'=>'test-key','football.api_football_key'=>'','football.season'=>2026,'football.refresh_mode'=>'auto','football.spacing_ms'=>0]);
        Cache::flush();Http::preventStrayRequests();$this->travelTo(now()->utc()->setDate(2026,10,30)->setTime(12,0));
        $this->fixture=['id'=>1001,'date'=>'2026-10-30T11:30:00Z','round'=>'Regular Season - 9','league'=>['id'=>52695,'season'=>2026],'state'=>['description'=>'First half','score'=>['current'=>'0 - 0']],'homeTeam'=>['id'=>1,'name'=>'A'],'awayTeam'=>['id'=>2,'name'=>'B']];
    }
    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }
    private function teamLineup(int $id): array {
        $n=0;$rows=[];
        foreach([1,4,3,3] as $size){$row=[];for($i=0;$i<$size;$i++){$n++;$row[]=['id'=>$id*100+$n,'name'=>'Player '.$id.'-'.$n,'number'=>$n,'position'=>$n===1?'Goalkeeper':'Defender'];}$rows[]=$row;}
        return ['id'=>$id,'formation'=>'4-3-3','initialLineup'=>$rows,'substitutes'=>[['id'=>$id*100+20,'name'=>'Bench '.$id,'number'=>20,'position'=>'Forward']]];
    }
    private function fake(array|callable $lineups): void {
        Http::fake(['*/matches?*'=>fn()=>Http::response(['data'=>[$this->fixture],'pagination'=>['totalCount'=>1]]),'*/lineups/1001'=>is_callable($lineups)?$lineups:Http::response($lineups)]);
    }
    public function test_two_teams_and_bench_share_one_request_and_complete_lineups_stay_cached(): void {
        $this->fake(['homeTeam'=>$this->teamLineup(1),'awayTeam'=>$this->teamLineup(2)]);
        $this->getJson('/api/matches')->assertOk();Http::assertSentCount(1);
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('lineups.home.formation','4-3-3')->assertJsonCount(4,'lineups.away.rows')->assertJsonPath('lineups.home.substitutes.0.position','Attaquant')->assertJsonPath('nextLookupAt',null)->assertDontSee('test-key');
        $this->getJson('/api/matches/1001/lineups',['x-l1-manual-refresh'=>'1'])->assertOk();Http::assertSentCount(2);
        $this->travel(20)->minutes();
        $this->getJson('/api/matches/1001/lineups')->assertOk();
        Http::assertSentCount(3); // Calendrier renouvelé, pas les compositions complètes.
    }
    public function test_missing_lineups_are_cached_for_fifteen_minutes_then_can_appear(): void {
        $complete=false;
        $this->fake(function()use(&$complete){return Http::response($complete?['homeTeam'=>$this->teamLineup(1),'awayTeam'=>$this->teamLineup(2)]:[]);});
        $first=$this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('lineups.home',null)->assertJsonPath('lineups.away',null);
        $this->assertGreaterThan(now()->getTimestampMs(),$first->json('nextLookupAt'));
        $this->getJson('/api/matches/1001/lineups',['x-l1-manual-refresh'=>'1'])->assertOk();Http::assertSentCount(2);
        $complete=true;$this->travel(16)->minutes();
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('lineups.home.formation','4-3-3');Http::assertSentCount(4);
    }
    public function test_early_postponed_and_unknown_matches_do_not_query_lineups(): void {
        $this->fixture['date']='2026-10-30T20:00:00Z';$this->fixture['state']['description']='Not started';$this->fake([]);
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('fetchedAt',null)->assertJsonPath('lineups.home',null);
        $this->getJson('/api/matches/9999/lineups')->assertNotFound();Http::assertSentCount(1);
        $this->travelTo(now()->setTime(19,30));
        $this->getJson('/api/matches/1001/lineups')->assertOk();Http::assertSentCount(2);
        Cache::flush();$this->fixture['state']['description']='Postponed';
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('nextLookupAt',null);Http::assertSentCount(3);
    }
    public function test_incomplete_or_wrong_team_lineups_never_become_an_invented_eleven(): void {
        $home=$this->teamLineup(1);$home['initialLineup'][1][0]['id']=$home['initialLineup'][0][0]['id'];
        $payload=['homeTeam'=>$home,'awayTeam'=>$this->teamLineup(2)];
        $this->fake(function()use(&$payload){return Http::response($payload);});
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('lineups.home',null)->assertJsonPath('lineups.away.formation','4-3-3');
        Cache::flush();$payload=['homeTeam'=>$this->teamLineup(99),'awayTeam'=>$this->teamLineup(2)];
        $this->getJson('/api/matches/1001/lineups')->assertStatus(502);
        $bench=$this->teamLineup(1);$bench['substitutes'][]=$bench['initialLineup'][0][0];$bench['substitutes'][]=['name'=>''];
        $normalized=Normalize::lineup(['homeTeam'=>$bench],1);
        $this->assertCount(1,$normalized['substitutes']);
    }
    public function test_pause_and_budget_do_not_allow_extra_requests_and_stale_cache_is_retained(): void {
        $this->fake(['homeTeam'=>$this->teamLineup(1),'awayTeam'=>$this->teamLineup(2)]);
        $this->getJson('/api/matches')->assertOk();
        config(['football.daily_limit'=>1]);
        $this->getJson('/api/matches/1001/lineups')->assertStatus(429);Http::assertSentCount(1);
        config(['football.daily_limit'=>90]);
        $this->getJson('/api/matches/1001/lineups')->assertOk();Http::assertSentCount(2);
        config(['football.refresh_mode'=>'pause']);
        $this->travel(31)->days();
        $this->getJson('/api/matches/1001/lineups')->assertOk()->assertJsonPath('stale',true)->assertJsonPath('lineups.home.formation','4-3-3');Http::assertSentCount(2);
    }
}
