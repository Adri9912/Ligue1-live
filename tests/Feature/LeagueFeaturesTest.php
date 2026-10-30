<?php
namespace Tests\Feature;
use App\Football\Clubs;
use App\Football\Normalize;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
class LeagueFeaturesTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();config(['football.provider'=>'highlightly','football.highlightly_key'=>'fake-hl','football.api_football_key'=>'fake-af','football.season'=>2026,'football.spacing_ms'=>0]);Cache::flush();Http::preventStrayRequests();$this->travelTo(now()->setDate(2026,10,30)->setTime(12,0));
    }
    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }
    private function team(int $id=73119,string $name='Paris Saint Germain'): array { return ['id'=>$id,'name'=>$name]; }
    private function matchRow(int $id=1001,string $state='Finished'): array {
        return ['id'=>$id,'date'=>'2026-10-'.(27-($id-1001)).'T19:00:00Z','round'=>'Regular Season - 8','league'=>['id'=>52695,'season'=>2026],'state'=>['description'=>$state,'score'=>['current'=>$state==='Not started'?null:'2 - 0']],'homeTeam'=>$this->team(),'awayTeam'=>$this->team(68864,'Lyon')];
    }
    private function table(): array {
        $rows=[];foreach([$this->team(),$this->team(68864,'Lyon')] as $i=>$t)$rows[]=['position'=>$i+1,'team'=>$t,'points'=>3-$i,'total'=>['scoredGoals'=>2,'receivedGoals'=>0,'games'=>1,'wins'=>1,'draws'=>0,'loses'=>0]];
        return ['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>$rows]]];
    }
    private function lineup(): array {
        $n=0;$rows=[];foreach([1,4,3,3] as $count){$row=[];for($i=0;$i<$count;$i++){$n++;$row[]=['id'=>$n,'name'=>'Player '.$n,'number'=>$n,'position'=>$n===1?'Goalkeeper':'Defender'];}$rows[]=$row;}
        return ['homeTeam'=>['id'=>73119,'formation'=>'4-3-3','initialLineup'=>$rows]];
    }
    public function test_details_events_stats_missing_scores_and_current_season_validation(): void {
        $m=$this->matchRow();$m['statistics']=[['team'=>['id'=>73119],'statistics'=>[['displayName'=>'Possession','value'=>0.54],['displayName'=>'Shots on target','value'=>0]]]];
        $m['events']=[['time'=>'45 + 2','type'=>'Own Goal','team'=>['id'=>73119],'player'=>'A']];
        Http::fake(['*/matches?*'=>Http::response(['data'=>[$m],'pagination'=>['totalCount'=>1]]),'*/matches/1001'=>Http::response([$m])]);
        $this->getJson('/api/matches/1001')->assertOk()->assertJsonPath('events.0.extra',2)->assertJsonPath('events.0.type','Goal')->assertJsonPath('stats.0.home','54%')->assertJsonPath('stats.0.away',null)->assertJsonPath('stats.1.home',0);
        $this->getJson('/api/matches/9999')->assertNotFound();Http::assertSentCount(2);
        $this->assertSame([],Normalize::apiStats([],['home'=>['id'=>1],'away'=>['id'=>2]]));
    }
    public function test_api_football_events_and_stats_have_the_same_shape(): void {
        config(['football.provider'=>'api-football']);
        $m=['fixture'=>['id'=>1,'date'=>'2026-10-27T19:00:00Z','status'=>['short'=>'FT']],'teams'=>['home'=>['id'=>1,'name'=>'A'],'away'=>['id'=>2,'name'=>'B']],'goals'=>['home'=>1,'away'=>0],'league'=>['round'=>'Regular Season - 8']];
        Http::fake(['*/fixtures?*'=>Http::response(['response'=>[$m]]),'*/fixtures/events*'=>Http::response(['response'=>[['time'=>['elapsed'=>45,'extra'=>1],'team'=>['id'=>1],'player'=>['name'=>'A'],'type'=>'Goal','detail'=>'Normal Goal']]]),'*/fixtures/statistics*'=>Http::response(['response'=>[['team'=>['id'=>1],'statistics'=>[['type'=>'Total Shots','value'=>0]]]]])]);
        $this->getJson('/api/matches/1')->assertOk()->assertJsonPath('events.0.extra',1)->assertJsonPath('stats.0.home',0)->assertJsonPath('stats.0.away',null);
    }
    public function test_lineup_search_is_bounded_validates_rows_and_can_resume(): void {
        $matches=array_map(fn($id)=>$this->matchRow($id),range(1001,1004));$lineup=$this->lineup();
        Http::fake(['*/matches?*'=>Http::response(['data'=>$matches,'pagination'=>['totalCount'=>4]]),'*/lineups/1004'=>Http::response($lineup),'*/lineups/*'=>Http::response([])]);
        $this->getJson('/api/clubs/73119/lineup')->assertOk()->assertJsonPath('lineup',null)->assertJsonCount(3,'checkedMatchIds')->assertJsonPath('nextOffset',3);
        $this->getJson('/api/clubs/73119/lineup?offset=3')->assertOk()->assertJsonPath('lineup.formation','4-3-3')->assertJsonPath('lineup.match.id',1004);
        $this->getJson('/api/clubs/73119/lineup?offset=oops')->assertStatus(400);
        $this->getJson('/api/clubs/73119/lineup?offset=401')->assertStatus(400);
        $lineup['homeTeam']['initialLineup'][1][0]['id']=1;$this->assertNull(Normalize::lineup($lineup,73119));
        $this->assertNull(Normalize::lineup([],73119));Http::assertSentCount(5);
    }
    public function test_club_squad_photo_profiles_membership_and_cache(): void {
        $registry=[['team'=>['id'=>85,'name'=>'Paris Saint Germain','founded'=>1970,'national'=>false],'venue'=>['name'=>'Parc des Princes','city'=>'Paris']]];
        $players=[['id'=>20,'name'=>'Attacker','position'=>'Attacker','number'=>null],['id'=>10,'name'=>'Keeper','position'=>'Goalkeeper','number'=>1]];
        Http::fake(['*/standings?*'=>Http::response($this->table()),'*/teams?country*'=>Http::response(['response'=>$registry]),'*/players/squads*'=>Http::response(['response'=>[['team'=>['id'=>85],'players'=>$players]]]),'*/players/profiles*'=>Http::response(['response'=>[['player'=>['id'=>10,'firstname'=>'Full','lastname'=>'Keeper','nationality'=>'Belgium','birth'=>['date'=>'2000-01-01','country'=>'England'],'height'=>'190 cm']]]])]);
        $this->getJson('/api/clubs/73119')->assertOk()->assertJsonPath('club.id',73119)->assertJsonPath('squad.0.id',10)->assertJsonPath('squad.0.photo','https://media.api-sports.io/football/players/10.png')->assertJsonCount(14,'honours.seasons')->assertJsonPath('squad.1.number',null);
        $this->getJson('/api/clubs/73119/players/10')->assertOk()->assertJsonPath('player.name','Full Keeper')->assertJsonPath('player.height','190 cm')->assertJsonPath('player.nationality','Belgique')->assertJsonPath('player.birthCountry','Angleterre');
        $this->getJson('/api/clubs/73119/players/999')->assertNotFound();
        $this->getJson('/api/clubs/999')->assertNotFound();Http::assertSentCount(4);
        $this->assertSame('2016–2017',Clubs::honours(91)['seasons'][0]);$this->assertFalse(Clubs::honours(999)['known']);
    }
    public function test_player_countries_use_french_labels_without_fabricating_unknown_values(): void {
        foreach(['Belgium'=>'Belgique','Brazil'=>'Brésil','Netherlands'=>'Pays-Bas','England'=>'Angleterre','Northern-Ireland'=>'Irlande du Nord','Ivory-Coast'=>'Côte d’Ivoire','USA'=>'États-Unis','South Korea'=>'Corée du Sud','Belgique'=>'Belgique','Unknown Federation'=>'Unknown Federation'] as $input=>$expected)$this->assertSame($expected,Clubs::country($input));
        $this->assertNull(Clubs::country(null));$this->assertNull(Clubs::country(''));
        Http::assertNothingSent();
    }
    public function test_archives_share_quota_and_never_change_current_season(): void {
        $team=['id'=>85,'name'=>'Paris'];$row=['rank'=>1,'team'=>$team,'points'=>84,'goalsDiff'=>57,'form'=>'W','all'=>['played'=>34,'win'=>26,'draw'=>6,'lose'=>2,'goals'=>['for'=>92,'against'=>35]]];
        Http::fake(['*/standings?*'=>Http::response(['response'=>[['league'=>['standings'=>[[$row]]]]]]),'*/players/topscorers*'=>Http::response(['response'=>[['player'=>['id'=>1,'name'=>'A'],'statistics'=>[['league'=>['id'=>61],'team'=>$team,'goals'=>['total'=>0,'assists'=>null]]]]]])]);
        $this->getJson('/api/retro')->assertOk()->assertJsonPath('available',true)->assertJsonPath('season',2026);
        $this->getJson('/api/retro/2024/standings')->assertOk()->assertJsonPath('archiveSeason',2024)->assertJsonPath('season',2026)->assertJsonPath('source','highlightly')->assertJsonPath('standings.0.points',84);
        $this->getJson('/api/retro/2024/scorers')->assertOk()->assertJsonPath('players.0.goals',0)->assertJsonPath('retroBudget.used',2);
        $this->getJson('/api/retro/2024/standings')->assertOk();$this->getJson('/api/retro/2025/standings')->assertNotFound();Http::assertSentCount(2);
    }
    public function test_demo_is_explicit_and_all_match_links_work_without_requests(): void {
        config(['football.highlightly_key'=>'','football.api_football_key'=>'']);
        $list=$this->getJson('/api/matches')->assertOk()->assertJsonPath('mode','demo')->json();
        $this->getJson('/api/overview')->assertOk()->assertJsonCount(18,'teams');
        foreach($list['matches'] as $m)$this->getJson('/api/matches/'.$m['id'])->assertOk()->assertJsonPath('fixture.id',$m['id'])->assertJsonPath('mode','demo');
        $this->getJson('/api/players/scorers')->assertOk()->assertJsonCount(12,'players');
        $this->getJson('/api/matches/99999')->assertNotFound();$this->getJson('/api/players/unknown')->assertNotFound();Http::assertNothingSent();
    }
}
