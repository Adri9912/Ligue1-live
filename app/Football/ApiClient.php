<?php
namespace App\Football;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Cache\LockTimeoutException;
class ApiClient
{
    public function __construct(public readonly string $provider)
    {
        if (!in_array($provider, ['highlightly','api-football','balldontlie'], true)) throw new ApiError('Fournisseur invalide.', 500);
        if (config('football.daily_limit') < 1 || config('football.daily_limit') > 100) throw new ApiError('Budget quotidien invalide.', 500);
    }
    public function key(): string { return (string) config(match($this->provider) { 'highlightly'=>'football.highlightly_key','balldontlie'=>'football.balldontlie_key',default=>'football.api_football_key' }); }
    public static function mode(): string {
        $mode=config('football.refresh_mode', 'auto');
        return in_array($mode,['auto','manual','pause'],true) ? $mode : 'auto';
    }
    private function canCall(bool $manual): bool { return self::mode() !== 'pause' && (self::mode() === 'auto' || $manual); }
    public function budget(): array
    {
        $date = now()->utc()->format('Y-m-d'); $q = Cache::get('football:quota:'.$this->provider, []);
        $used = ($q['day']??null) === $date ? ($q['used']??0) : 0;
        return ['used'=>$used, 'limit'=>config('football.daily_limit'), 'remaining'=>max(0,config('football.daily_limit')-$used), 'resetAt'=>(int) now()->utc()->addDay()->startOfDay()->getTimestampMs(), 'providerRemaining'=>($q['day']??null)===$date ? ($q['providerRemaining']??null) : null, 'observedAt'=>($q['day']??null)===$date ? ($q['observedAt']??null) : null];
    }
    private function responseKey(string $path, array $params): string {
        return 'football:response:'.$this->provider.':'.hash('sha256', $path.'?'.http_build_query($params));
    }
    public function cached(string $path, array $params): ?array {
        return Cache::get($this->responseKey($path,$params));
    }
    private function validateRosterResponse(array $body,string $path,array $params,?string $signature):void {
        if(!isset($body['data'])||!is_array($body['data'])||!array_is_list($body['data'])||!$body['data'])throw new ApiError('Les effectifs ne sont pas disponibles pour cette saison.');
        // ponytail: une page de 100 joueurs par club ; refuser une page tronquée plutôt que présenter un effectif incomplet.
        if(isset($body['meta']['next_cursor']))throw new ApiError('L’effectif reçu est incomplet. Réessayez ultérieurement.');
        $seen=[];
        foreach($body['data'] as $row) {
            if(!is_array($row))throw new ApiError('Réponse BALLDONTLIE incohérente.');
            $id=$path==='rosters'?($row['player']['id']??null):($row['id']??null);
            if(!is_int($id)||$id<1||isset($seen[$id]))throw new ApiError('Réponse BALLDONTLIE incohérente.');
            $seen[$id]=true;
            if($path==='rosters'&&(($row['team_id']??null)!==($params['team_id']??null)||(string)($row['season']??'')!==$signature))throw new ApiError('Effectif reçu pour un autre club ou une autre saison.');
            if($path==='rosters') {
                if(!is_bool($row['is_active']??null))throw new ApiError('Statut du joueur non vérifiable.');
                foreach(['display_name','first_name','last_name','date_of_birth','citizenship','height','weight'] as $field)if(isset($row['player'][$field])&&!is_string($row['player'][$field]))throw new ApiError('Fiche joueur incohérente.');
                if(isset($row['position'])&&!is_string($row['position']))throw new ApiError('Poste du joueur incohérent.');
                if(isset($row['jersey_number'])&&!is_string($row['jersey_number'])&&!is_int($row['jersey_number']))throw new ApiError('Numéro du joueur incohérent.');
            }
            if($path==='teams'&&(!is_string($row['name']??null)||!trim($row['name'])))throw new ApiError('Liste des clubs incohérente.');
        }
    }
    public function request(string $path, array $params, int|callable $fresh, bool $manual = false, ?string $signature = null): array
    {
        $cacheKey = $this->responseKey($path,$params);
        $valid = fn ($e) => is_callable($fresh) ? $fresh($e) : Schedule::now()-$e['time'] < $fresh;
        $result = fn ($e, $stale) => ['data'=>$e['data'], 'fetchedAt'=>$e['time'], 'stale'=>$stale, 'signature'=>$e['signature']??null];
        $cached = Cache::get($cacheKey);
        if ($cached && ($valid($cached) || !$this->canCall($manual))) return $result($cached, !$valid($cached));
        if (!$this->canCall($manual)) throw new ApiError('Actualisation en pause ou en mode manuel. Utilisez le bouton d’actualisation.',409);
        if (!$this->key()) throw new ApiError('La clé du fournisseur est absente.',503);
        try {
            // ponytail: verrou par fournisseur sur un serveur ; cache partagé si plusieurs serveurs.
            return Cache::lock('football:request-lock:'.$this->provider, 120)->block(60, function () use ($path,$params,$cacheKey,$cached,$valid,$result,$manual,$signature) {
                $latest = Cache::get($cacheKey);
                if ($latest && $valid($latest)) return $result($latest, false);
                try {
                    // Un refus d’accès confirmé ne doit pas provoquer un nouvel appel à chaque visite.
                    if($message=Cache::get('football:access-block:'.$this->provider))throw new ApiError($message,403);
                    if($this->provider==='balldontlie') {
                        $minute=array_values(array_filter(Cache::get('football:minute:balldontlie',[]),fn($at)=>$at>Schedule::now()-60000));
                        if(count($minute)>=5||Cache::get('football:backoff:balldontlie'))throw new ApiError('Les effectifs sont sollicités. Réessayez dans une minute.',429);
                    }
                    $qKey = 'football:quota:'.$this->provider;
                    $q = Cache::get($qKey, []);
                    $delay = $this->provider==='balldontlie'?0:max(0, (int) config('football.spacing_ms')-(Schedule::now()-($q['lastCall']??0)));
                    if ($delay) usleep($delay*1000);
                    if (!$this->canCall($manual)) throw new ApiError('Actualisation interrompue par le réglage Manuel ou Pause.',409);
                    $day = now()->utc()->format('Y-m-d');
                    if (($q['day']??null) !== $day) $q = ['day'=>$day,'used'=>0];
                    if (($q['used']??0) >= config('football.daily_limit') || ($q['providerRemaining']??null) === 0) throw new ApiError('Budget quotidien ou quota fournisseur atteint.',429);
                    $q['used'] = ($q['used']??0)+1; $q['lastCall'] = Schedule::now(); Cache::forever($qKey,$q);
                    if (!$this->canCall($manual)) { $q['used']--; Cache::forever($qKey,$q); throw new ApiError('Actualisation interrompue avant l’envoi.',409); }
                    if($this->provider==='balldontlie')Cache::put('football:minute:balldontlie',[...$minute,Schedule::now()],61);
                    $base = match($this->provider) { 'highlightly'=>'https://soccer.highlightly.net/','balldontlie'=>'https://api.balldontlie.io/ligue1/v1/',default=>'https://v3.football.api-sports.io/' };
                    $header = match($this->provider) { 'highlightly'=>'x-rapidapi-key','balldontlie'=>'Authorization',default=>'x-apisports-key' };
                    $response = Http::timeout(20)->connectTimeout(10)->withoutRedirecting()->withHeaders([$header=>$this->key()])->get($base.$path,$params);
                    $remaining = $response->header('x-ratelimit-requests-remaining');
                    if ($this->provider!=='balldontlie' && $remaining !== null && preg_match('/^\d+$/',$remaining)) { $q['providerRemaining']=(int)$remaining; $q['observedAt']=Schedule::now(); Cache::forever($qKey,$q); }
                    $body = $response->json();
                    if($this->provider==='balldontlie') {
                        if(in_array($response->status(),[401,403],true)) {
                            $message='L’accès aux effectifs BALLDONTLIE est indisponible. Les anciennes données restent consultables.';
                            Cache::put('football:access-block:balldontlie',$message,900);throw new ApiError($message,403);
                        }
                        if($response->status()===429)Cache::put('football:backoff:balldontlie',true,60);
                    }
                    if ($this->provider==='api-football' && is_array($body) && is_string($body['errors']['access']??null) && str_contains(strtolower($body['errors']['access']),'suspended')) {
                        $message='L’accès à API-Football est suspendu. Les données déjà enregistrées restent consultables.';
                        Cache::put('football:access-block:'.$this->provider,$message,900);
                        throw new ApiError($message,403);
                    }
                    if (!$response->successful()) throw new ApiError('Le fournisseur de données est indisponible ou son quota est atteint.', $response->status()===429 ? 429 : 502);
                    if (!is_array($body) || !empty($body['errors'])) throw new ApiError('Accès API refusé ou réponse inattendue.');
                    if ($this->provider === 'balldontlie') {
                        $this->validateRosterResponse($body,$path,$params,$signature);$data=$body;
                    } elseif ($this->provider === 'highlightly') {
                        if (preg_match('/hidden|restricted|limited/i',$body['plan']['message']??'')) throw new ApiError('Données masquées par l’offre Highlightly.');
                        if (!array_is_list($body) && !isset($body['data']) && !isset($body['groups']) && !isset($body['homeTeam']) && !isset($body['awayTeam'])) throw new ApiError('Réponse Highlightly inattendue.');
                        $data = $body;
                    } else {
                        $data = $body['response']??null;
                        if (!is_array($data)) throw new ApiError('Réponse API-Football inattendue.');
                    }
                    $entry = ['data'=>$data,'time'=>Schedule::now(),'signature'=>$signature]; Cache::forever($cacheKey,$entry);
                    return $result($entry,false);
                } catch (\Throwable $e) {
                    if ($latest ?? $cached) return $result($latest ?? $cached,true);
                    throw $e instanceof ApiError ? $e : new ApiError('Connexion au fournisseur impossible.');
                }
            });
        } catch (LockTimeoutException $e) {
            if ($cached) return $result($cached,true);
            throw new ApiError('Le fournisseur est occupé. Réessayez dans quelques instants.',503);
        }
    }
}
