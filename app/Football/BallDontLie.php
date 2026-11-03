<?php
namespace App\Football;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
class BallDontLie {
    public readonly ApiClient $client;
    public function __construct() { $this->client=new ApiClient('balldontlie'); }
    public static function configured():bool { return (string)config('football.balldontlie_key')!==''; }
    private static function name(string $name):string {
        $key=preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($name)));
        return match($key) {
            'psg','parissg','parissaintgermain'=>'psg',
            'asmonaco'=>'monaco','ajauxerre'=>'auxerre','angerssco'=>'angers','lemansfc'=>'lemans',
            'staderennais','staderennaisfc','rennesfc'=>'rennes','stadebrestois29'=>'brest','lehavreac'=>'lehavre','estactroyes'=>'troyes',
            'olympiquelyonnais'=>'lyon','olympiquedemarseille'=>'marseille','rcstrasbourgalsace'=>'strasbourg','rclens'=>'lens','losclille'=>'lille','ogcnice'=>'nice','fclorient'=>'lorient',
            default=>$key,
        };
    }
    public function roster(array $team,int $season,bool $manual=false,bool $cachedOnly=false):?array {
        $params=['season'=>$season];
        $registry=$cachedOnly?$this->client->cached('teams',$params):$this->client->request('teams',$params,86400000,$manual);
        if(!$registry)return null;
        $name=self::name($team['fullName']??$team['name']);
        $matches=array_values(array_filter($registry['data']['data']??[],fn($t)=>self::name($t['name'])===$name));
        if(count($matches)!==1) {
            if($cachedOnly)return null;
            throw new ApiError('La correspondance de ce club avec BALLDONTLIE reste à vérifier.',422);
        }
        $id=$matches[0]['id'];$params=['team_id'=>$id,'per_page'=>100];
        $fresh=fn($e)=>($e['signature']??null)===(string)$season&&Schedule::now()-$e['time']<86400000;
        $r=$cachedOnly?$this->client->cached('rosters',$params):$this->client->request('rosters',$params,$fresh,$manual,(string)$season);
        if(!$r)return null;
        $rows=$r['data']['data']??[];
        if(($r['signature']??null)!==(string)$season||collect($rows)->contains(fn($row)=>($row['team_id']??null)!==$id||($row['season']??null)!==$season)) {
            if($cachedOnly)return null;
            throw new ApiError('Aucun effectif fiable disponible pour cette saison.');
        }
        $photos=$this->cachedPhotos($team);$players=[];
        $oldId=Clubs::cachedApiFootballId($team);$oldClient=new ApiClient('api-football');
        $oldEntry=$oldId?$oldClient->cached('players/squads',['team'=>$oldId]):null;
        $legacy=$oldEntry&&Schedule::now()-$oldEntry['time']<90*86400000?Clubs::cachedLegacySquad($team):[];
        foreach($rows as $row) {
            if(($row['is_active']??null)!==true)continue;
            $p=$row['player'];$fullName=trim($p['display_name']??implode(' ',array_filter([$p['first_name']??null,$p['last_name']??null])));
            if($fullName==='')continue;
            $birth=$p['date_of_birth']??null;
            if(!is_string($birth)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$birth,$d)||!checkdate((int)$d[2],(int)$d[3],(int)$d[1])||$birth>now()->format('Y-m-d'))$birth=null;
            $position=$row['position']??'';
            $photoMatches=$birth?($photos[self::name($fullName).'|'.$birth]??[]):[];
            $number=$row['jersey_number']??null;
            $age=$birth?CarbonImmutable::parse($birth)->age:null;
            $recognized=[];
            if(count($photoMatches)!==1&&$age!==null&&is_scalar($number)&&ctype_digit((string)$number)) {
                foreach($legacy as $old) {
                    if($old['number']!==(int)$number||!is_int($old['age'])||abs($old['age']-$age)>1||!self::samePlayerName($old['name'],$fullName,$p['last_name']??null))continue;
                    $profile=$oldClient->cached('players/profiles',['player'=>$old['id']]);
                    $knownBirth=collect($profile['data']??[])->firstWhere('player.id',$old['id'])['player']['birth']['date']??null;
                    if($knownBirth!==null&&$knownBirth!==$birth)continue;
                    $recognized[]=$old;
                }
                if(count($recognized)===1)$photoMatches=[$recognized[0]['photo']];
            }
            $aliases=count($recognized)===1?[$recognized[0]['name']]:[];
            $lookup=Portraits::register($p['id'],$fullName,$birth);
            $portrait=count($photoMatches)===1?$photoMatches[0]:($lookup?Portraits::cached($p['id']):null);
            $players[]=['id'=>$p['id'],'source'=>'balldontlie','name'=>$fullName,'aliases'=>$aliases,'number'=>is_scalar($number)&&ctype_digit((string)$number)?(int)$number:null,
                'position'=>Clubs::position($position),'order'=>match($position) { 'Goalkeeper'=>0,'Defender'=>1,'Midfielder'=>2,'Forward','Attacker'=>3,default=>4 },
                'photo'=>$portrait,'photoLookup'=>$lookup,'birthDate'=>$birth,'age'=>$age,
                'nationality'=>Clubs::country($p['citizenship']??null),'height'=>self::height($p['height']??null),'weight'=>self::weight($p['weight']??null),'birthPlace'=>null,'birthCountry'=>null];
        }
        $collator=new \Collator('fr');usort($players,fn($a,$b)=>$a['order']<=>$b['order']?:($a['number']??PHP_INT_MAX)<=>($b['number']??PHP_INT_MAX)?:$collator->compare($a['name'],$b['name']));
        return ['squad'=>$players,'squadSource'=>'BALLDONTLIE','squadTeamId'=>$id,'squadFetchedAt'=>$r['fetchedAt']??$r['time'],
            'squadStale'=>$cachedOnly?!$fresh($r):($r['stale']||$registry['stale']),'squadBudget'=>$this->client->budget()];
    }
    private static function samePlayerName(string $old,string $full,?string $surname):bool {
        if(self::name($old)===self::name($full))return true;
        // Un surnom à un mot doit être le nom de famille explicitement transmis.
        if($surname&&self::name($old)===self::name($surname))return true;
        $words=fn($name)=>preg_split('/[^a-z0-9]+/',Str::lower(Str::ascii($name)),-1,PREG_SPLIT_NO_EMPTY);
        $a=$words($old);$b=$words($full);
        return count($a)>1&&count($b)>1&&strlen($a[0])===1&&$a[0]===$b[0][0]&&array_slice($a,1)===array_slice($b,1);
    }
    private function cachedPhotos(array $team):array {
        $photos=[];$old=new ApiClient('api-football');
        foreach(Clubs::cachedLegacySquad($team) as $player) {
            $cached=$old->cached('players/profiles',['player'=>$player['id']]);
            $raw=collect($cached['data']??[])->firstWhere('player.id',$player['id'])['player']??null;
            if(!$raw||empty($raw['birth']['date']))continue;
            $name=trim(implode(' ',array_filter([$raw['firstname']??null,$raw['lastname']??null])))?:($raw['name']??'');
            if($name!=='')$photos[self::name($name).'|'.$raw['birth']['date']][]=Clubs::photo($player['id']);
        }
        return $photos;
    }
    private static function height(?string $value):?string {
        if(!$value||!preg_match('/^(\d)\x27\s*(\d{1,2})(?:"|″)?$/u',trim($value),$m)||(int)$m[2]>11)return null;
        $cm=round(((int)$m[1]*12+(int)$m[2])*2.54);
        return $cm>=100&&$cm<=250?$cm.' cm':null;
    }
    private static function weight(?string $value):?string {
        if(!$value||!preg_match('/^(\d+(?:\.\d+)?)\s*lbs?$/i',trim($value),$m))return null;
        $kg=round((float)$m[1]*0.45359237);
        return $kg>=35&&$kg<=200?$kg.' kg':null;
    }
}
