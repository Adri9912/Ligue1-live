<?php
namespace App\Football;
class Normalize
{
    public static function standings(array $data): array
    {
        return array_map(fn($r)=>['rank'=>$r['rank'],'team'=>League::team($r['team']),'points'=>$r['points'],'difference'=>$r['goalsDiff'],'form'=>$r['form']??'','played'=>$r['all']['played'],'wins'=>$r['all']['win'],'draws'=>$r['all']['draw'],'losses'=>$r['all']['lose'],'goalsFor'=>$r['all']['goals']['for'],'goalsAgainst'=>$r['all']['goals']['against']],$data[0]['league']['standings'][0]??[]);
    }
    public static function players(array $data): array
    {
        $rows=[];
        foreach ($data as $item) {
            $stat=collect($item['statistics']??[])->firstWhere('league.id',61);
            if (!$stat || !isset($stat['team'],$item['player']['id'])) continue;
            $rows[]=['id'=>$item['player']['id'],'name'=>$item['player']['name'],'team'=>League::team($stat['team']),'goals'=>$stat['goals']['total']??null,'assists'=>$stat['goals']['assists']??null];
        }
        return $rows;
    }
    public static function highlightlyDetails(array $m): array
    {
        $names=['Possession'=>'Possession','Shots on target'=>'Tirs cadrés','Corners'=>'Corners','Fouls'=>'Fautes','Yellow cards'=>'Cartons jaunes','Red cards'=>'Cartons rouges','Goalkeeper saves'=>'Arrêts'];
        $value=function($id,$key) use($m) {
            $team=collect($m['statistics']??[])->firstWhere('team.id',$id);
            $v=collect($team['statistics']??[])->firstWhere('displayName',$key)['value']??null;
            return $v===null?null:($key==='Possession'?round($v*100).'%':$v);
        };
        $events=array_map(function($e) {
            preg_match('/^(\d+)(?:\s*\+\s*(\d+))?/',(string)($e['time']??''),$minute);
            $type=$e['type'];$normal=str_contains($type,'Card')?'Card':($type==='Substitution'?'subst':(preg_match('/Goal|Penalty/',$type)?'Goal':$type));
            return ['minute'=>isset($minute[1])?(int)$minute[1]:null,'extra'=>isset($minute[2])?(int)$minute[2]:null,'teamId'=>$e['team']['id']??null,'player'=>$e['player']??'Joueur non renseigné','type'=>$normal,'detail'=>$type==='Penalty missed'?'Missed Penalty':$type];
        },$m['events']??[]);
        $stats=[];foreach($names as $key=>$label) { $h=$value($m['homeTeam']['id'],$key);$a=$value($m['awayTeam']['id'],$key);if($h!==null||$a!==null)$stats[]=['label'=>$label,'home'=>$h,'away'=>$a]; }
        return ['fixture'=>League::highlightlyFixture($m),'events'=>$events,'stats'=>$stats];
    }
    public static function apiStats(array $data,array $fixture): array
    {
        $names=['Ball Possession'=>'Possession','Total Shots'=>'Tirs','Shots on Goal'=>'Tirs cadrés','Corner Kicks'=>'Corners','Fouls'=>'Fautes','Yellow Cards'=>'Cartons jaunes','Red Cards'=>'Cartons rouges','Goalkeeper Saves'=>'Arrêts'];
        $home=collect($data)->firstWhere('team.id',$fixture['home']['id'])['statistics']??[];
        $away=collect($data)->firstWhere('team.id',$fixture['away']['id'])['statistics']??[];
        $stats=[];foreach($names as $key=>$label) { $h=collect($home)->firstWhere('type',$key)['value']??null;$a=collect($away)->firstWhere('type',$key)['value']??null;if($h!==null||$a!==null)$stats[]=['label'=>$label,'home'=>$h,'away'=>$a]; }return $stats;
    }
    public static function lineup(array $body,int $clubId): ?array
    {
        $root=array_is_list($body)?($body[0]??[]):$body;
        $team=collect([$root['homeTeam']??[], $root['awayTeam']??[]])->firstWhere('id',$clubId);
        if(!is_string($team['formation']??null)||!is_array($team['initialLineup']??null))return null;
        $shape=explode('-',$team['formation']);
        foreach($shape as $n)if(!ctype_digit($n)||(int)$n<1||(int)$n>5)return null;
        $shape=array_map('intval',$shape);if(array_sum($shape)!==10)return null;
        $counts=[1,...$shape];$rows=$team['initialLineup'];if(count($rows)!==count($counts))return null;
        $unique=[];$result=[];
        foreach($rows as $i=>$row) {
            if(!is_array($row)||count($row)!==$counts[$i])return null;
            $line=[];
            foreach($row as $p) {
                if(!is_array($p)||!is_string($p['name']??null)||!trim($p['name']))return null;
                $key=isset($p['id'])?'id:'.$p['id']:$p['name'].':'.($p['number']??'');if(isset($unique[$key]))return null;$unique[$key]=true;
                $line[]=['id'=>$p['id']??null,'name'=>$p['name'],'number'=>$p['number']??null,'position'=>Clubs::position($p['position']??'')];
            }$result[]=$line;
        }
        return count($unique)===11?['formation'=>$team['formation'],'rows'=>$result]:null;
    }
}
