<?php
namespace Tests\Feature;
use App\Football\ApiClient;
use App\Football\ApiError;
use App\Football\BallDontLie;
use App\Football\Clubs;
use App\Football\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class BallDontLieTest extends TestCase {
    private bool $providerFailed=false;
    private ?array $teamsOverride=null;
    protected function setUp():void {
        parent::setUp();config(['football.provider'=>'highlightly','football.highlightly_key'=>'fake-hl','football.api_football_key'=>'fake-old','football.balldontlie_key'=>'private-bdl-test','football.season'=>2026,'football.spacing_ms'=>0,'football.refresh_mode'=>'auto','football.daily_limit'=>90]);
        Cache::flush();Http::preventStrayRequests();$this->travelTo(now()->setDate(2026,11,3)->setTime(12,0));
    }
    protected function tearDown():void { $this->travelBack();parent::tearDown(); }
    private function cache(string $provider,string $path,array $params,array $data,?string $signature=null):void {
        Cache::forever('football:response:'.$provider.':'.hash('sha256',$path.'?'.http_build_query($params)),['time'=>Schedule::now(),'data'=>$data,'signature'=>$signature]);
    }
    private function player(int $id=100,string $name='Keeper Test',string $position='Goalkeeper',bool $active=true):array {
        return ['team_id'=>61,'season'=>2026,'is_active'=>$active,'jersey_number'=>'1','position'=>$position,'player'=>['id'=>$id,'display_name'=>$name,'date_of_birth'=>'2000-01-01','citizenship'=>'France','height'=>'6\' 2"','weight'=>'180 lbs']];
    }
    private function teams():array { return ['data'=>[['id'=>61,'name'=>'Angers'],['id'=>67,'name'=>'Lyon'],['id'=>73,'name'=>'Paris Saint-Germain']]]; }
    private function stub(array $rows):void {
        $table=['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>[['position'=>1,'team'=>['id'=>90011,'name'=>'Angers'],'points'=>3,'total'=>['scoredGoals'=>2,'receivedGoals'=>0,'games'=>1,'wins'=>1,'draws'=>0,'loses'=>0]]]]]];
        Http::fake(function($r)use($table,$rows) {
            if(str_contains($r->url(),'soccer.highlightly.net/standings'))return Http::response($table);
            if($this->providerFailed)return Http::response(['error'=>'private-bdl-test'],401);
            return Http::response(str_contains($r->url(),'/teams?')?($this->teamsOverride??$this->teams()):['data'=>$rows]);
        });
    }
    public function test_current_squad_profiles_cache_and_provider_identity_are_independent_from_old_api():void {
        $attack=$this->player(101,'Attacker Test','Forward');$attack['jersey_number']='9';$this->stub([$attack,$this->player(),$this->player(102,'Former Test','Defender',false)]);
        $r=$this->getJson('/api/clubs/90011')->assertOk()->assertJsonCount(2,'squad')->assertJsonPath('squad.0.id',100)->assertJsonPath('squad.0.source','balldontlie')->assertJsonPath('squad.0.position','Gardien')->assertJsonPath('squad.0.number',1)->assertJsonPath('squad.0.photo',null)->assertJsonPath('squad.1.position','Attaquant')->assertJsonPath('squadSource','BALLDONTLIE')->assertJsonPath('club.id',90011)->assertJsonPath('club.founded',null)->assertDontSee('private-bdl-test');
        $this->getJson('/api/clubs/90011/players/100?source=balldontlie')->assertOk()->assertJsonPath('player.height','188 cm')->assertJsonPath('player.weight','82 kg')->assertJsonPath('player.nationality','France')->assertJsonPath('player.age',26)->assertJsonPath('playerSource','BALLDONTLIE');
        $this->getJson('/api/clubs/90011/players/102?source=balldontlie')->assertNotFound();
        $this->getJson('/api/clubs/90011/players/100?source=api-football')->assertStatus(409);
        $this->getJson('/api/clubs/90011/players/100')->assertStatus(409);
        $this->getJson('/api/clubs/90011/players/100?source=unexpected')->assertUnprocessable();
        $this->getJson('/api/clubs/90011')->assertOk();Http::assertSentCount(3);
        Http::assertSent(fn($r)=>str_contains($r->url(),'balldontlie.io')&&$r->hasHeader('Authorization','private-bdl-test')&&!str_contains($r->url(),'private-bdl-test'));
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'api-sports.io'));
        $this->assertSame(1,(new ApiClient('highlightly'))->budget()['used']);$this->assertSame(2,(new ApiClient('balldontlie'))->budget()['used']);$this->assertSame(0,(new ApiClient('api-football'))->budget()['used']);
    }
    public function test_cached_club_information_and_titles_survive_without_old_provider_calls():void {
        $this->cache('api-football','teams',['country'=>'France'],[['team'=>['id'=>77,'name'=>'Angers','founded'=>1919],'venue'=>['name'=>'Stade enregistré','city'=>'Angers']]]);
        $this->travel(31)->days();$this->stub([$this->player()]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('club.founded',1919)->assertJsonPath('club.stadium','Stade enregistré')->assertJsonPath('clubSource','API-Football (cache)')->assertJsonPath('clubStale',true)->assertJsonCount(0,'honours.seasons');
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'api-sports.io'));
    }
    public function test_wrong_season_wrong_team_duplicates_and_partial_pages_never_replace_good_cache():void {
        $params=['team_id'=>61,'per_page'=>100];$client=new ApiClient('balldontlie');$row=$this->player();
        $wrongSeason=[...$row,'season'=>2025];$wrongTeam=[...$row,'team_id'=>67];
        foreach([['data'=>[$wrongSeason]],['data'=>[$wrongTeam]],['data'=>[$row,$row]],['data'=>[[...$row,'player'=>[...$row['player'],'display_name'=>['unexpected']]]]],['data'=>[[...$row,'is_active'=>'true']]],['data'=>[$row],'meta'=>['next_cursor'=>101]],['data'=>[]],['data'=>'invalid']] as $body) {
            Cache::flush();$this->cache('balldontlie','rosters',$params,['data'=>[$row]],'2026');$this->travel(25)->hours();
            Http::fake(['*'=>Http::response($body)]);
            $r=$client->request('rosters',$params,1000,false,'2026');$this->assertTrue($r['stale']);$this->assertSame([$row],$r['data']['data']);
            $this->assertSame([$row],$client->cached('rosters',$params)['data']['data']);
        }
    }
    public function test_inconsistent_or_ambiguous_club_mapping_does_not_use_another_squad():void {
        $this->stub([$this->player()]);$this->teamsOverride=['data'=>[['id'=>61,'name'=>'Angers'],['id'=>99,'name'=>'Angers SCO']]];
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squadErrorStatus',422)->assertJsonCount(0,'squad');
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'/rosters'));
    }
    public function test_missing_biography_numbers_and_unknown_positions_are_not_invented():void {
        $row=$this->player();$row['jersey_number']=null;$row['position']='Unknown';$row['player']=['id'=>100,'display_name'=>'Unknown Player','date_of_birth'=>'2030-02-30','height'=>'unknown','weight'=>null];$this->stub([$row]);
        $this->getJson('/api/clubs/90011/players/100?source=balldontlie')->assertOk()->assertJsonPath('player.number',null)->assertJsonPath('player.position','Poste non renseigné')->assertJsonPath('player.birthDate',null)->assertJsonPath('player.age',null)->assertJsonPath('player.nationality',null)->assertJsonPath('player.height',null)->assertJsonPath('player.weight',null)->assertJsonPath('player.photo',null);
    }
    public function test_minute_rate_limit_is_shared_including_across_midnight_and_provider_headers_are_not_daily_quota():void {
        $this->travelTo(now()->utc()->setTime(23,59,40));$client=new ApiClient('balldontlie');
        Http::fake(['*'=>Http::response($this->teams(),200,['x-ratelimit-requests-remaining'=>'0'])]);
        for($i=0;$i<5;$i++)$client->request('teams',['season'=>2020+$i],1000);
        $this->assertNull($client->budget()['providerRemaining']);$this->travel(25)->seconds();
        try{(new ApiClient('balldontlie'))->request('teams',['season'=>2026],1000,true);$this->fail('Sixième appel envoyé');}catch(ApiError $e){$this->assertSame(429,$e->getStatusCode());}
        Http::assertSentCount(5);$this->travel(36)->seconds();$client->request('teams',['season'=>2026],1000);Http::assertSentCount(6);
    }
    public function test_failure_cooldown_and_stale_fallback_keep_dates_and_do_not_expose_keys():void {
        $this->stub([$this->player()]);$first=$this->getJson('/api/clubs/90011')->assertOk()->json();$this->travel(25)->hours();
        $this->providerFailed=true;
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squadStale',true)->assertJsonPath('squadFetchedAt',$first['squadFetchedAt'])->assertJsonCount(1,'squad')->assertDontSee('private-bdl-test');
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squadStale',true);
        Http::assertSentCount(5);
        $client=new ApiClient('balldontlie');
        try{$client->request('rosters',['team_id'=>67,'per_page'=>100],1000,true,'2026');$this->fail('Refus ignoré');}catch(ApiError $e){$this->assertSame(403,$e->getStatusCode());$this->assertStringNotContainsString('private-bdl-test',$e->getMessage());}
        Http::assertSentCount(5);
    }
    public function test_pause_manual_and_cache_only_lineup_reading_never_trigger_hidden_requests():void {
        $this->stub([$this->player()]);$this->getJson('/api/clubs/90011')->assertOk();
        $cached=Clubs::cachedSquad(['name'=>'Angers']);$this->assertCount(1,$cached);$this->assertSame('balldontlie',$cached[0]['source']);Http::assertSentCount(3);
        $this->travel(25)->hours();config(['football.refresh_mode'=>'pause']);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squadStale',true);Http::assertSentCount(3);
        config(['football.refresh_mode'=>'manual']);$this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squadStale',true);Http::assertSentCount(3);
        $this->getJson('/api/clubs/90011',['x-l1-manual-refresh'=>'1'])->assertOk()->assertJsonPath('squadStale',false);Http::assertSentCount(6);
        config(['football.season'=>2027]);$this->assertSame([],Clubs::cachedSquad(['name'=>'Angers']));Http::assertSentCount(6);
    }
    public function test_upstream_429_waits_before_retry_and_the_daily_safety_limit_is_separate():void {
        $client=new ApiClient('balldontlie');Http::fakeSequence()->pushStatus(429)->push($this->teams());
        for($i=0;$i<2;$i++) {
            try{$client->request('teams',['season'=>2026],1000,true);$this->fail('Quota ignoré');}catch(ApiError $e){$this->assertSame(429,$e->getStatusCode());}
        }
        Http::assertSentCount(1);$this->assertSame(1,$client->budget()['used']);
        $this->travel(61)->seconds();$client->request('teams',['season'=>2026],1000);Http::assertSentCount(2);
        config(['football.daily_limit'=>2]);
        try{$client->request('teams',['season'=>2027],1000,true);$this->fail('Plafond journalier ignoré');}catch(ApiError $e){$this->assertSame(429,$e->getStatusCode());}
        Http::assertSentCount(2);
    }
    public function test_all_eighteen_clubs_resolve_their_own_roster_and_keep_main_provider_ids():void {
        $pairs=[['Paris Saint Germain','Paris Saint-Germain'],['Lille','Lille'],['Lyon','Lyon'],['Paris FC','Paris FC'],['Rennes FC','Stade Rennais'],['Monaco','AS Monaco'],['Marseille','Marseille'],['Nice','Nice'],['Lens','Lens'],['Stade Brestois 29','Brest'],['Lorient','Lorient'],['Toulouse','Toulouse'],['Strasbourg','Strasbourg'],['Auxerre','AJ Auxerre'],['Angers','Angers'],['Estac Troyes','Troyes'],['Le Mans','Le Mans'],['LE Havre AC','Le Havre AC']];
        $teams=[];$standings=[];
        foreach($pairs as $i=>[$main,$other]) {
            $teams[]=['id'=>200+$i,'name'=>$other];
            $standings[]=['position'=>$i+1,'team'=>['id'=>1000+$i,'name'=>$main],'points'=>0,'total'=>['scoredGoals'=>0,'receivedGoals'=>0,'games'=>0,'wins'=>0,'draws'=>0,'loses'=>0]];
        }
        Http::fake(function($request)use($teams,$standings) {
            if(str_contains($request->url(),'soccer.highlightly.net'))return Http::response(['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>$standings]]]);
            if(str_contains($request->url(),'/teams?'))return Http::response(['data'=>$teams]);
            return Http::response(['data'=>[[...$this->player(300+(int)$request['team_id']),'team_id'=>(int)$request['team_id']]]]);
        });
        foreach($pairs as $i=>$pair) {
            $this->getJson('/api/clubs/'.(1000+$i))->assertOk()->assertJsonPath('club.id',1000+$i)->assertJsonPath('squadTeamId',200+$i)->assertJsonPath('squad.0.id',500+$i)->assertJsonPath('squad.0.source','balldontlie');
            $this->travel(61)->seconds();
        }
        Http::assertSentCount(20);Http::assertNotSent(fn($r)=>str_contains($r->url(),'thesportsdb')||str_contains($r->url(),'api-sports'));
    }
    public function test_cached_abbreviated_portrait_requires_club_shirt_age_and_unique_identity():void {
        $this->cache('api-football','teams',['country'=>'France'],[['team'=>['id'=>80,'name'=>'Angers']]]);
        $old=['id'=>9001,'name'=>'K. Test','position'=>'Goalkeeper','number'=>1,'age'=>25];
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>[$old]]]);$this->stub([$this->player()]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo','https://media.api-sports.io/football/players/9001.png')->assertJsonPath('squad.0.aliases.0','K. Test');
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>[$old,[...$old,'id'=>9002]]]]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo',null);
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>[[...$old,'age'=>20]]]]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo',null);
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>[[...$old,'number'=>9]]]]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo',null);
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>[$old]]]);$this->travel(91)->days();config(['football.refresh_mode'=>'pause']);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo',null);Http::assertSentCount(3);
    }
    public function test_photos_require_unique_full_name_and_birth_date_match_not_provider_id_coincidence():void {
        $this->cache('api-football','teams',['country'=>'France'],[['team'=>['id'=>80,'name'=>'Angers']]]);
        $old=[['id'=>9001,'name'=>'Keeper Test','position'=>'Goalkeeper','number'=>1]];
        $this->cache('api-football','players/squads',['team'=>80],[['team'=>['id'=>80],'players'=>$old]]);
        $profile=['id'=>9001,'firstname'=>'Keeper','lastname'=>'Test','birth'=>['date'=>'2000-01-01']];
        $this->cache('api-football','players/profiles',['player'=>9001],[['player'=>$profile]]);$this->stub([$this->player()]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.id',100)->assertJsonPath('squad.0.photo','https://media.api-sports.io/football/players/9001.png');
        $profile['birth']['date']='1999-01-01';$this->cache('api-football','players/profiles',['player'=>9001],[['player'=>$profile]]);
        $this->getJson('/api/clubs/90011')->assertOk()->assertJsonPath('squad.0.photo',null);Http::assertSentCount(3);
    }
}
