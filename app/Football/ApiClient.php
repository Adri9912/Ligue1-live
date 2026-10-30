<?php
namespace App\Football;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Cache\LockTimeoutException;
class ApiClient
{
    public function __construct(public readonly string $provider)
    {
        if (!in_array($provider, ['highlightly','api-football'], true)) throw new ApiError('Fournisseur invalide.', 500);
        if (config('football.daily_limit') < 1 || config('football.daily_limit') > 100) throw new ApiError('Budget quotidien invalide.', 500);
    }
    public function key(): string { return (string) config($this->provider === 'highlightly' ? 'football.highlightly_key' : 'football.api_football_key'); }
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
    public function request(string $path, array $params, int|callable $fresh, bool $manual = false, ?string $signature = null): array
    {
        $cacheKey = 'football:response:'.$this->provider.':'.hash('sha256', $path.'?'.http_build_query($params));
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
                    $qKey = 'football:quota:'.$this->provider;
                    $q = Cache::get($qKey, []);
                    $delay = max(0, (int) config('football.spacing_ms')-(Schedule::now()-($q['lastCall']??0)));
                    if ($delay) usleep($delay*1000);
                    if (!$this->canCall($manual)) throw new ApiError('Actualisation interrompue par le réglage Manuel ou Pause.',409);
                    $day = now()->utc()->format('Y-m-d');
                    if (($q['day']??null) !== $day) $q = ['day'=>$day,'used'=>0];
                    if (($q['used']??0) >= config('football.daily_limit') || ($q['providerRemaining']??null) === 0) throw new ApiError('Budget quotidien ou quota fournisseur atteint.',429);
                    $q['used'] = ($q['used']??0)+1; $q['lastCall'] = Schedule::now(); Cache::forever($qKey,$q);
                    if (!$this->canCall($manual)) { $q['used']--; Cache::forever($qKey,$q); throw new ApiError('Actualisation interrompue avant l’envoi.',409); }
                    $base = $this->provider === 'highlightly' ? 'https://soccer.highlightly.net/' : 'https://v3.football.api-sports.io/';
                    $header = $this->provider === 'highlightly' ? 'x-rapidapi-key' : 'x-apisports-key';
                    $response = Http::timeout(20)->connectTimeout(10)->withoutRedirecting()->withHeaders([$header=>$this->key()])->get($base.$path,$params);
                    $remaining = $response->header('x-ratelimit-requests-remaining');
                    if ($remaining !== null && preg_match('/^\d+$/',$remaining)) { $q['providerRemaining']=(int)$remaining; $q['observedAt']=Schedule::now(); Cache::forever($qKey,$q); }
                    if (!$response->successful()) throw new ApiError('Le fournisseur de données est indisponible ou son quota est atteint.', $response->status()===429 ? 429 : 502);
                    $body = $response->json();
                    if (!is_array($body) || !empty($body['errors'])) throw new ApiError('Accès API refusé ou réponse inattendue.');
                    if ($this->provider === 'highlightly') {
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
