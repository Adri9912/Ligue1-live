<?php
namespace App\Football;
use Illuminate\Support\Facades\Cache;
class League
{
    public readonly ApiClient $client;
    public readonly int $season;
    public function __construct()
    {
        $this->client = new ApiClient(config('football.provider'));
        $this->season = (int) (config('football.season') ?: (now()->utc()->month >= 7 ? now()->year : now()->year-1));
        if ($this->season < 2000 || $this->season > 2100) throw new ApiError('Saison invalide.',500);
    }
    private function summaryKey(string $kind): string { return 'football:summary:'.$this->client->provider.':'.$this->season.':'.$kind; }
    private function params(): array { return $this->client->provider === 'highlightly' ? ['leagueId'=>52695,'season'=>$this->season] : ['league'=>61,'season'=>$this->season]; }
    public static function team(array $t): array { return ['id'=>$t['id'],'name'=>$t['name'],'code'=>$t['code']??mb_strtoupper(mb_substr($t['name'],0,3)),'logo'=>$t['logo']??null,'color'=>'#365d88']; }
    public static function fixture(array $f): array
    {
        preg_match('/(\d+)$/',(string)($f['league']['round']??''),$round);
        return ['id'=>$f['fixture']['id'],'home'=>self::team($f['teams']['home']),'away'=>self::team($f['teams']['away']),'status'=>$f['fixture']['status']['short'],'elapsed'=>$f['fixture']['status']['elapsed']??null,'homeGoals'=>$f['goals']['home']??null,'awayGoals'=>$f['goals']['away']??null,'date'=>$f['fixture']['date'],'round'=>isset($round[1])?(int)$round[1]:null,'venue'=>$f['fixture']['venue']['name']??null];
    }
    public static function highlightlyFixture(array $m): array
    {
        $statuses = ['Not started'=>'NS','First half'=>'1H','Second half'=>'2H','Half time'=>'HT','Extra time'=>'ET','Break time'=>'BT','Penalties'=>'P','Finished'=>'FT','Finished after penalties'=>'PEN','Finished after extra time'=>'AET','Postponed'=>'PST','Suspended'=>'SUSP','Cancelled'=>'CANC','Awarded'=>'AWD','Interrupted'=>'INT','Abandoned'=>'ABD','In progress'=>'LIVE'];
        preg_match('/^(\d+)\s*-\s*(\d+)$/',$m['state']['score']['current']??'',$score);
        return self::fixture(['fixture'=>['id'=>$m['id'],'date'=>$m['date'],'status'=>['short'=>$statuses[$m['state']['description']??'']??'TBD','elapsed'=>$m['state']['clock']??null],'venue'=>$m['venue']??[]],'teams'=>['home'=>$m['homeTeam'],'away'=>$m['awayTeam']],'goals'=>['home'=>isset($score[1])?(int)$score[1]:null,'away'=>isset($score[2])?(int)$score[2]:null],'league'=>['round'=>$m['round']??null]]);
    }
    public function matches(bool $manual=false): array
    {
        $times=[]; $stale=false; $records=[];
        if ($this->client->provider === 'highlightly') {
            $total=1;
            for ($offset=0;$offset<$total;$offset+=100) {
                $page=$this->client->request('matches',[...$this->params(),'limit'=>100,'offset'=>$offset],fn($e)=>Schedule::fixturesFresh(['time'=>$e['time'],'data'=>array_map(self::highlightlyFixture(...),$e['data']['data']??[])],Schedule::now(),$manual),$manual);
                $body=$page['data']; $total=$body['pagination']['totalCount']??null;
                if (!is_int($total) || $total<1 || $total>1000 || !is_array($body['data']??null) || !$body['data']) throw new ApiError('Calendrier Highlightly incomplet.');
                foreach ($body['data'] as $m) {
                    if (($m['league']['id']??null)!==52695 || ($m['league']['season']??null)!==$this->season) throw new ApiError('Compétition ou saison inattendue.');
                    $records[$m['id']]=self::highlightlyFixture($m);
                }
                $times[]=$page['fetchedAt']; $stale=$stale||$page['stale'];
            }
            if (count($records)!==$total) throw new ApiError('Calendrier incomplet : pages manquantes.');
            $matches=array_values($records);
        } else {
            $page=$this->client->request('fixtures',$this->params(),fn($e)=>Schedule::fixturesFresh(['time'=>$e['time'],'data'=>array_map(self::fixture(...),$e['data'])],Schedule::now(),$manual),$manual);
            $matches=array_map(self::fixture(...),$page['data']);$times[]=$page['fetchedAt'];$stale=$page['stale'];
        }
        usort($matches,fn($a,$b)=>strcmp($a['date'],$b['date']));
        Cache::forever($this->summaryKey('matches'),['data'=>$matches,'time'=>min($times)]);
        return ['matches'=>$matches,'fetchedAt'=>min($times),'stale'=>$stale];
    }
    public function overview(bool $manual=false): array
    {
        $signature=Schedule::signature(Cache::get($this->summaryKey('matches'),['data'=>[]])['data']);
        $fresh=fn($e)=>Schedule::standingsFresh($e,$signature,Schedule::now());
        $daily=fn($e)=>Schedule::sameDay($e['time'],Schedule::now());
        if ($this->client->provider==='api-football') {
            $coverage=$this->client->request('leagues',['id'=>61,'season'=>$this->season],$daily,$manual);
            if (!collect($coverage['data'][0]['seasons']??[])->contains('year',$this->season)) throw new ApiError('Cette saison n’est pas accessible avec cette offre.',422);
        }
        $result=$this->client->request('standings',$this->params(),$fresh,$manual,$signature);
        if ($this->client->provider==='highlightly') {
            $body=$result['data'];
            if (($body['league']['id']??null)!==52695 || ($body['league']['season']??null)!==$this->season) throw new ApiError('Classement reçu pour une autre saison ou compétition.');
            $group=collect($body['groups']??[])->first(fn($g)=>count($g['standings']??[])===18) ?? ($body['groups'][0]??[]);
            if (empty($group['standings'])) throw new ApiError('Classement indisponible.');
            $standings=array_map(fn($r)=>['rank'=>$r['position'],'team'=>self::team($r['team']),'points'=>$r['points'],'difference'=>$r['total']['scoredGoals']-$r['total']['receivedGoals'],'form'=>'','played'=>$r['total']['games'],'wins'=>$r['total']['wins'],'draws'=>$r['total']['draws'],'losses'=>$r['total']['loses'],'goalsFor'=>$r['total']['scoredGoals'],'goalsAgainst'=>$r['total']['receivedGoals']],$group['standings']);
            $teams=array_column($standings,'team'); $stale=$result['stale'];
        } else {
            $standings=array_map(fn($r)=>['rank'=>$r['rank'],'team'=>self::team($r['team']),'points'=>$r['points'],'difference'=>$r['goalsDiff'],'form'=>$r['form']??'','played'=>$r['all']['played'],'wins'=>$r['all']['win'],'draws'=>$r['all']['draw'],'losses'=>$r['all']['lose'],'goalsFor'=>$r['all']['goals']['for'],'goalsAgainst'=>$r['all']['goals']['against']],$result['data'][0]['league']['standings'][0]??[]);
            $clubs=$this->client->request('teams',$this->params(),$daily,$manual);
            $teams=array_map(fn($r)=>[...self::team($r['team']),'founded'=>$r['team']['founded']??null,'stadium'=>$r['venue']['name']??null,'city'=>$r['venue']['city']??null],$clubs['data']);
            $stale=$result['stale']||$coverage['stale']||$clubs['stale'];
        }
        Cache::forever($this->summaryKey('standings'),['time'=>$result['fetchedAt'],'signature'=>$result['signature']]);
        return ['teams'=>$teams,'standings'=>$standings,'fetchedAt'=>$result['fetchedAt'],'stale'=>$stale];
    }
    public function metadata(): array
    {
        $matches=Cache::get($this->summaryKey('matches'));$table=Cache::get($this->summaryKey('standings'));$now=Schedule::now();
        $next=Schedule::nextFixtures($matches,$now);$signature=Schedule::signature($matches['data']??[]);
        if ($table && (!Schedule::standingsFresh($table,$signature,$now) || $table['signature']!==$signature)) $next=min($next,max($now,$table['time']+Schedule::HOUR));
        $budget=$this->client->budget();
        return ['mode'=>$this->client->key()?'api':'demo','source'=>$this->client->key()?$this->client->provider:'demo','season'=>$this->season,'refreshMinutes'=>10,'refreshMode'=>ApiClient::mode(),'budget'=>$budget,'nextRefreshAt'=>ApiClient::mode()==='auto'&&$budget['remaining']>0&&$budget['providerRemaining']!==0?$next:null];
    }
}
