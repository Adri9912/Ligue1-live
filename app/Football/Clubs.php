<?php
namespace App\Football;
use Illuminate\Support\Str;
class Clubs
{
    public readonly ApiClient $client;
    private const TITLES=[85=>[1986,1994,2013,2014,2015,2016,2018,2019,2020,2022,2023,2024,2025,2026],81=>[1937,1948,1971,1972,1989,1990,1991,1992,2010],91=>[1961,1963,1978,1982,1988,1997,2000,2017],79=>[1946,1954,2011,2021],80=>[2002,2003,2004,2005,2006,2007,2008],84=>[1951,1952,1956,1959],116=>[1998],108=>[1996],95=>[1979],94=>[],114=>[],97=>[],96=>[],106=>[],77=>[],1298=>[],110=>[],111=>[]];
    private const CORRECTIONS=[96=>['founded'=>1970,'source'=>'https://billetterie.om.fr/fr/histoire/adversaires/om-toulouse-fc'],91=>['founded'=>1924,'source'=>'https://www.asmonaco.com/fr/club/histoire/naissance-de-l-as-monaco'],114=>['stadium'=>'Stade Jean-Bouin','source'=>'https://parisfc.fr/club/communique-officiel-29/']];
    public function __construct(public readonly League $league) { $this->client=new ApiClient('api-football'); }
    private static function name(string $s): string {
        $name=preg_replace('/[^a-z0-9]/','',strtolower(Str::ascii($s)));
        return ['rennesfc'=>'rennes','lehavreac'=>'lehavre','estactroyes'=>'troyes','stadebrestois29'=>'brest'][$name]??$name;
    }
    private static function record(string $name, array $registry): ?array {
        $name=self::name($name);
        $matching=array_values(array_filter($registry,fn($r)=>!($r['team']['national']??false)&&self::name($r['team']['name'])===$name));
        return count($matching)===1?$matching[0]:null;
    }
    public static function withHomeStadiums(array $matches): array {
        // Réutilisation seule : ne déclenche jamais d'appel ni de recherche d'effectif.
        $cached=(new ApiClient('api-football'))->cached('teams',['country'=>'France']);
        if(!$cached || Schedule::now()-$cached['time']>=30*86400000)return $matches;
        return array_map(function($match) use($cached) {
            if(!empty($match['venue']))return $match;
            $record=self::record($match['home']['name'],$cached['data']);
            if(!$record)return $match;
            $stadium=self::CORRECTIONS[$record['team']['id']]['stadium']??$record['venue']['name']??null;
            if(!is_string($stadium)||!trim($stadium))return $match;
            // Le stade habituel n'est pas une confirmation du lieu de cette rencontre.
            return [...$match,'venue'=>$stadium,'venueSource'=>'club'];
        },$matches);
    }
    public static function photo(int $id): ?string { return $id>0?'https://media.api-sports.io/football/players/'.$id.'.png':null; }
    public static function position(string $p): string { return ['Goalkeeper'=>'Gardien','Defender'=>'Défenseur','Midfielder'=>'Milieu','Attacker'=>'Attaquant','Forward'=>'Attaquant'][$p]??'Poste non renseigné'; }
    public static function country(?string $country): ?string
    {
        if($country===null||trim($country)==='')return null;
        static $names=null;
        if($names===null) {
            $names=[];
            foreach(\ResourceBundle::create('en','ICUDATA-region')->get('Countries') as $code=>$english) {
                if(preg_match('/^[A-Z]{2}$/D',$code))$names[self::name($english)]=\Locale::getDisplayRegion('und_'.$code,'fr');
            }
            foreach(['Ivory Coast'=>'CI','DR Congo'=>'CD','Congo DR'=>'CD','USA'=>'US','Turkey'=>'TR'] as $english=>$code)$names[self::name($english)]=\Locale::getDisplayRegion('und_'.$code,'fr');
            foreach(['England'=>'Angleterre','Scotland'=>'Écosse','Wales'=>'Pays de Galles','Northern Ireland'=>'Irlande du Nord'] as $english=>$french)$names[self::name($english)]=$french;
        }
        return $names[self::name($country)]??$country;
    }
    public static function squad(array $players): array
    {
        $order=['Goalkeeper'=>0,'Defender'=>1,'Midfielder'=>2,'Attacker'=>3,'Forward'=>3];
        $rows=array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'number'=>$p['number']??null,'age'=>$p['age']??null,'photo'=>self::photo($p['id']),'position'=>self::position($p['position']??''),'order'=>$order[$p['position']??'']??4],$players);
        $collator=new \Collator('fr');usort($rows,fn($a,$b)=>$a['order']<=>$b['order']?:($a['number']??PHP_INT_MAX)<=>($b['number']??PHP_INT_MAX)?:$collator->compare($a['name'],$b['name']));return $rows;
    }
    public static function honours(int $id): array { return ['known'=>array_key_exists($id,self::TITLES),'seasons'=>array_map(fn($end)=>($end-1).'–'.$end,array_reverse(self::TITLES[$id]??[])),'source'=>'https://ligue1.com/en/articles/l1_article_289-le-palmares-des-champions-de-ligue-1','verifiedAt'=>'2026-10-27']; }
    public function detail(int $id,bool $manual=false): array
    {
        $current=collect($this->league->overview($manual)['teams'])->firstWhere('id',$id);
        if(!$current)throw new ApiError('Club introuvable dans la saison actuelle de Ligue 1.',404);
        if(!$this->client->key())throw new ApiError('La clé API-Football est nécessaire pour les effectifs.',503);
        $registry=$this->client->request('teams',['country'=>'France'],30*86400000,$manual);
        $record=self::record($current['name'],$registry['data']);
        if(!$record)throw new ApiError('La correspondance de ce club entre les fournisseurs reste à vérifier.',422);
        $apiId=$record['team']['id'];$correction=self::CORRECTIONS[$apiId]??[];
        $club=[...League::team($record['team']),'id'=>$id,'founded'=>$correction['founded']??$record['team']['founded']??null,'stadium'=>$correction['stadium']??$record['venue']['name']??null,'city'=>$record['venue']['city']??null,'correctionSource'=>$correction['source']??null];
        $squad=[];$error='';$status=null;$time=$registry['fetchedAt'];$stale=$registry['stale'];
        try {
            $r=$this->client->request('players/squads',['team'=>$apiId],86400000,$manual);$row=collect($r['data'])->firstWhere('team.id',$apiId);
            if(empty($row['players']))$error='L’effectif n’est pas disponible pour ce club.';else $squad=self::squad($row['players']);
            $time=$r['fetchedAt'];$stale=$stale||$r['stale'];
        } catch(ApiError $e) { $error=$e->getMessage();$status=$e->getStatusCode(); }
        return ['club'=>$club,'squad'=>$squad,'squadError'=>$error,'squadErrorStatus'=>$status,'honours'=>self::honours($apiId),'fetchedAt'=>$time,'stale'=>$stale,'clubSource'=>'API-Football','clubBudget'=>$this->client->budget()];
    }
    public function player(int $clubId,int $id,bool $manual=false): array
    {
        $details=$this->detail($clubId,$manual);if($details['squadError'])throw new ApiError($details['squadError'],$details['squadErrorStatus']??503);
        $member=collect($details['squad'])->firstWhere('id',$id);if(!$member)throw new ApiError('Ce joueur ne figure pas dans l’effectif actuel de ce club.',404);
        $r=$this->client->request('players/profiles',['player'=>$id],7*86400000,$manual);$raw=collect($r['data'])->firstWhere('player.id',$id)['player']??null;
        if(!$raw)throw new ApiError('La fiche de ce joueur n’est pas disponible.',404);
        $name=trim(implode(' ',array_filter([$raw['firstname']??null,$raw['lastname']??null])))?:($raw['name']??$member['name']);
        return ['player'=>['id'=>$id,'name'=>$name,'shortName'=>$member['name'],'position'=>$member['position'],'number'=>$member['number'],'photo'=>self::photo($id),'age'=>$raw['age']??$member['age'],'birthDate'=>$raw['birth']['date']??null,'birthPlace'=>$raw['birth']['place']??null,'birthCountry'=>self::country($raw['birth']['country']??null),'nationality'=>self::country($raw['nationality']??null),'height'=>$raw['height']??null,'weight'=>$raw['weight']??null],'club'=>$details['club'],'fetchedAt'=>$r['fetchedAt'],'stale'=>$r['stale']||$details['stale'],'clubBudget'=>$this->client->budget()];
    }
}
