<?php
namespace App\Football;
class Retro
{
    private readonly ApiClient $client;
    public const SEASONS=[2024,2023,2022];
    public function __construct() { $this->client=new ApiClient('api-football'); }
    public function metadata(): array { return ['available'=>(bool)$this->client->key(),'seasons'=>self::SEASONS,'archiveSource'=>'API-Football','retroBudget'=>$this->client->key()?$this->client->budget():null]; }
    public function get(int $year,string $section,bool $manual=false): array
    {
        if(!in_array($year,self::SEASONS,true))throw new ApiError('Cette saison ne fait pas partie des archives proposées.',404);
        $paths=['standings'=>'standings','matches'=>'fixtures','scorers'=>'players/topscorers','assists'=>'players/topassists'];
        if(!isset($paths[$section]))throw new ApiError('Rubrique rétro introuvable.',404);
        if(!$this->client->key())throw new ApiError('La clé API-Football est nécessaire pour les archives.',503);
        $r=$this->client->request($paths[$section],['league'=>61,'season'=>$year],30*86400000,$manual);
        if($section==='standings') { $rows=Normalize::standings($r['data']);if(!$rows)throw new ApiError('Le classement de cette saison est indisponible.',422);$data=['standings'=>$rows]; }
        elseif($section==='matches') { $matches=array_values(array_filter($r['data'],fn($m)=>str_starts_with($m['league']['round']??'','Regular Season')));if(!$matches)throw new ApiError('Les résultats de cette saison sont indisponibles.',422);$data=['matches'=>array_map(League::fixture(...),$matches)]; }
        else { $metric=$section==='scorers'?'goals':'assists';$players=array_values(array_filter(Normalize::players($r['data']),fn($p)=>$p[$metric]!==null));usort($players,fn($a,$b)=>$b[$metric]<=>$a[$metric]);$data=['players'=>$players]; }
        return [...$data,'archiveSeason'=>$year,'archiveSource'=>'API-Football','fetchedAt'=>$r['fetchedAt'],'stale'=>$r['stale'],'retroBudget'=>$this->client->budget()];
    }
}
