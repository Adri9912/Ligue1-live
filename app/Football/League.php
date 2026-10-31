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
        if(!$this->client->key()) return (new Demo)->matches();
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
        $matches=Clubs::withHomeStadiums($matches);
        usort($matches,fn($a,$b)=>Schedule::timestamp($a['date'])<=>Schedule::timestamp($b['date']));
        Cache::forever($this->summaryKey('matches'),['data'=>$matches,'time'=>min($times)]);
        return ['matches'=>$matches,'fetchedAt'=>min($times),'stale'=>$stale];
    }
    public function overview(bool $manual=false): array
    {
        if(!$this->client->key()) return (new Demo)->overview();
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
            $standings=Normalize::standings($result['data']);
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
        return ['mode'=>$this->client->key()?'api':'demo','source'=>$this->client->key()?$this->client->provider:'demo','season'=>$this->season,'refreshMinutes'=>10,'refreshMode'=>ApiClient::mode(),'budget'=>$budget,'nextRefreshAt'=>$this->client->key()&&ApiClient::mode()==='auto'&&$budget['remaining']>0&&$budget['providerRemaining']!==0?$next:null];
    }

    public function detail(int $id,bool $manual=false): array
    {
        if(!$this->client->key()) return (new Demo)->detail($id);
        $list=$this->matches($manual);$fixture=collect($list['matches'])->firstWhere('id',$id);
        if(!$fixture)throw new ApiError('Match introuvable en Ligue 1 pour cette saison.',404);
        if(in_array($fixture['status'],['NS','TBD','PST','CANC'],true))return ['fixture'=>$fixture,'events'=>[],'stats'=>[],'fetchedAt'=>$list['fetchedAt'],'stale'=>$list['stale']];
        if($this->client->provider==='highlightly') {
            $result=$this->client->request('matches/'.$id,[],900000,$manual);$body=$result['data'];$match=array_is_list($body)?($body[0]??null):($body['data'][0]??null);
            if(!$match||($match['id']??null)!==$id||($match['league']['id']??null)!==52695||($match['league']['season']??null)!==$this->season)throw new ApiError('Détail de match Highlightly incohérent.');
            $details=Normalize::highlightlyDetails($match);
            $details['fixture']=Clubs::withHomeStadiums([$details['fixture']])[0];
            return [...$details,'fetchedAt'=>$result['fetchedAt'],'stale'=>$result['stale']||$list['stale']];
        }
        $events=$this->client->request('fixtures/events',['fixture'=>$id],900000,$manual);$stats=$this->client->request('fixtures/statistics',['fixture'=>$id],900000,$manual);
        return ['fixture'=>$fixture,'events'=>array_map(fn($e)=>['minute'=>$e['time']['elapsed']??null,'extra'=>$e['time']['extra']??null,'teamId'=>$e['team']['id'],'player'=>$e['player']['name']??'Joueur non renseigné','type'=>$e['type'],'detail'=>$e['detail']],$events['data']),'stats'=>Normalize::apiStats($stats['data'],$fixture),'fetchedAt'=>min($list['fetchedAt'],$events['fetchedAt'],$stats['fetchedAt']),'stale'=>$list['stale']||$events['stale']||$stats['stale']];
    }
    public function players(string $kind,bool $manual=false): array
    {
        if(!$this->client->key()) return (new Demo)->players($kind);
        if($this->client->provider==='highlightly')return ['players'=>[],'unavailable'=>'Highlightly ne fournit pas de classement global des buteurs et passeurs dans son API documentée. Aucun joueur d’une autre saison n’est affiché.','fetchedAt'=>Schedule::now(),'stale'=>false];
        $r=$this->client->request('players/'.($kind==='scorers'?'topscorers':'topassists'),$this->params(),fn($e)=>Schedule::sameDay($e['time'],Schedule::now()),$manual);
        $metric=$kind==='scorers'?'goals':'assists';$players=array_values(array_filter(Normalize::players($r['data']),fn($p)=>$p[$metric]!==null));usort($players,fn($a,$b)=>$b[$metric]<=>$a[$metric]);
        return ['players'=>$players,'fetchedAt'=>$r['fetchedAt'],'stale'=>$r['stale']];
    }
    public function lineup(int $clubId,int $offset=0,bool $manual=false): array
    {
        if($this->client->provider!=='highlightly')throw new ApiError('Les compositions nécessitent Highlightly.',503);
        if($offset<0||$offset>400)throw new ApiError('Position de recherche invalide.',400);
        $list=$this->matches($manual);$clubGames=array_filter($list['matches'],fn($m)=>$m['home']['id']===$clubId||$m['away']['id']===$clubId);
        if(!$clubGames)throw new ApiError('Club introuvable dans cette saison de Ligue 1.',404);
        $games=array_values(array_filter($clubGames,fn($m)=>in_array($m['status'],['FT','AET','PEN'],true)&&Schedule::timestamp($m['date'])<=Schedule::now()));
        usort($games,fn($a,$b)=>Schedule::timestamp($b['date'])<=>Schedule::timestamp($a['date'])?:$b['id']<=>$a['id']);$checked=[];$stale=$list['stale'];$time=$list['fetchedAt'];
        foreach(array_slice($games,$offset,3) as $match) {
            $r=$this->client->request('lineups/'.$match['id'],[],fn($e)=>Schedule::now()-$e['time']<(Normalize::lineup($e['data'],$clubId)?30*86400000:6*Schedule::HOUR),$manual);
            $checked[]=$match['id'];$stale=$stale||$r['stale'];$time=$r['fetchedAt'];$lineup=Normalize::lineup($r['data'],$clubId);
            if($lineup)return ['lineup'=>[...$lineup,'match'=>$match],'checkedMatchIds'=>$checked,'totalMatches'=>count($games),'nextOffset'=>null,'fetchedAt'=>$time,'stale'=>$stale,'lineupBudget'=>$this->client->budget(),'lineupSource'=>'Highlightly'];
        }
        $next=$offset+count($checked);
        return ['lineup'=>null,'checkedMatchIds'=>$checked,'totalMatches'=>count($games),'nextOffset'=>$next<count($games)?$next:null,'fetchedAt'=>$time,'stale'=>$stale,'lineupBudget'=>$this->client->budget(),'lineupSource'=>'Highlightly'];
    }
}
