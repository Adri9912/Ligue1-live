<?php
namespace App\Football;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
class Portraits {
    private static function name(string $name):string { return preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($name))); }
    public static function register(int $id,string $name,?string $birth):?string {
        if(!$birth)return null;
        $identity=['name'=>$name,'birth'=>$birth];$key='portraits:identity:'.$id;
        if(Cache::get($key)!==$identity)Cache::forever($key,$identity);
        return '/api/portraits/'.$id;
    }
    public static function cached(int $id):?string {
        $identity=Cache::get('portraits:identity:'.$id);
        if(!$identity)return null;
        return Cache::get('portraits:result:'.hash('sha256',json_encode($identity)))['photo']??null;
    }
    private static function url(mixed $url):?string {
        if(!is_string($url))return null;
        $p=parse_url($url);
        return ($p['scheme']??'')==='https'&&in_array($p['host']??'',['r2.thesportsdb.com','www.thesportsdb.com'],true)&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])&&preg_match('~^/images/media/player/(?:cutout|thumb)/[a-zA-Z0-9_-]+\.(?:png|jpg|jpeg|webp)$~D',$p['path']??'')?$url:null;
    }
    public static function get(int $id):array {
        $identity=Cache::get('portraits:identity:'.$id);
        if(!$identity)throw new ApiError('Portrait non disponible.',404);
        $key='portraits:result:'.hash('sha256',json_encode($identity));$cached=Cache::get($key);
        if($cached!==null)return $cached;
        if(ApiClient::mode()!=='auto')return ['photo'=>null,'source'=>'TheSportsDB'];
        $later=fn()=>['photo'=>null,'source'=>'TheSportsDB','retryAt'=>Schedule::now()+65000];
        try {
            // ponytail: verrou partagé sur ce serveur, comme pour les autres fournisseurs.
            return Cache::lock('portraits:request-lock',15)->block(10,function()use($identity,$key,$later) {
                if(($cached=Cache::get($key))!==null)return $cached;
                $minute=array_values(array_filter(Cache::get('portraits:minute',[]),fn($at)=>$at>Schedule::now()-60000));
                if(count($minute)>=25||Cache::get('portraits:backoff'))return $later();
                if(ApiClient::mode()!=='auto')return ['photo'=>null,'source'=>'TheSportsDB'];
                Cache::put('portraits:minute',[...$minute,Schedule::now()],61);
                try {
                    $response=Http::timeout(8)->connectTimeout(3)->withoutRedirecting()->get('https://www.thesportsdb.com/api/v1/json/123/searchplayers.php',['p'=>$identity['name']]);
                    if(!$response->successful()) { Cache::put('portraits:backoff',true,60);return $later(); }
                    $body=$response->json();
                    if(!is_array($body)||!array_key_exists('player',$body)) { Cache::put('portraits:backoff',true,60);return $later(); }
                    $rows=$body['player'];
                    if($rows!==null&&(!is_array($rows)||!array_is_list($rows))) { Cache::put('portraits:backoff',true,60);return $later(); }
                    $matches=array_values(array_filter($rows??[],fn($p)=>is_array($p)&&is_string($p['strPlayer']??null)&&self::name($p['strPlayer'])===self::name($identity['name'])&&($p['dateBorn']??null)===$identity['birth']));
                    $photo=count($matches)===1?(self::url($matches[0]['strCutout']??null)??self::url($matches[0]['strThumb']??null)):null;
                    $result=['photo'=>$photo,'source'=>'TheSportsDB'];Cache::put($key,$result,$photo?30*86400:86400);
                    return $result;
                } catch(\Throwable $e) { Cache::put('portraits:backoff',true,60);return $later(); }
            });
        } catch(LockTimeoutException $e) { return $later(); }
    }
}
