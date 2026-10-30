<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
Artisan::command('football:import-node-cache {--from= : Dossier du cache Node, par défaut .cache}', function () {
    $directory=$this->option('from') ?: base_path('.cache');$count=0;
    foreach(['api-football'=>['football.json','v3.football.api-sports.io'],'highlightly'=>['highlightly/football.json','soccer.highlightly.net']] as $provider=>[$file,$host]) {
        $path=$directory.'/'.$file;if(!is_file($path))continue;
        try { $disk=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR); } catch(\JsonException) { $this->error('Cache Node illisible : '.$provider);return 1; }
        Cache::lock('football:request-lock:'.$provider,120)->block(60,function() use($disk,$provider,$host,&$count) {
            foreach($disk['cache']??[] as $url=>$entry) {
                $parts=parse_url($url);if(($parts['host']??null)!==$host||!is_array($entry)||!isset($entry['time'],$entry['data']))continue;
                parse_str($parts['query']??'',$params);$path=ltrim($parts['path']??'','/');
                $key='football:response:'.$provider.':'.hash('sha256',$path.'?'.http_build_query($params));$existing=Cache::get($key);
                if(!$existing||$existing['time']<$entry['time']) { Cache::forever($key,$entry);$count++; }
            }
            $key='football:quota:'.$provider;$existing=Cache::get($key,[]);$today=now()->utc()->format('Y-m-d');
            if(($disk['day']??null)===$today) {
                $quota=($existing['day']??null)===$today?$existing:['day'=>$today,'used'=>0];
                $quota['used']=max($quota['used']??0,$disk['used']??0);
                if(($disk['providerQuota']['day']??null)===$today) {
                    $n=$disk['providerQuota']['remaining']??null;
                    if($n!==null)$quota['providerRemaining']=isset($quota['providerRemaining'])?min($quota['providerRemaining'],$n):$n;
                    $quota['observedAt']=max($quota['observedAt']??0,$disk['providerQuota']['observedAt']??0);
                }
                Cache::forever($key,$quota);
            }
        });
    }
    $this->info($count.' réponses importées. Aucun appel API. Les quotas existants ne sont jamais réduits.');return 0;
})->purpose('Importer le cache privé Node sans perdre les quotas déjà consommés');
