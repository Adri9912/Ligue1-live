<?php
namespace App\Http\Controllers;
use App\Football\ApiClient;
use App\Football\ApiError;
use App\Football\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
class RefreshController extends Controller
{
    private const COOKIE='l1-refresh-visitor';
    private const LIMIT=10;
    private function key(string $visitor): string { return 'football:manual-clicks:'.now()->utc()->format('Y-m-d').':'.hash('sha256',$visitor); }
    private function allowance(string $visitor): array {
        return ['limit'=>self::LIMIT,'remaining'=>max(0,self::LIMIT-(int)Cache::get($this->key($visitor),0)),'resetAt'=>(int)now()->utc()->addDay()->startOfDay()->getTimestampMs()];
    }
    public function status(Request $request) {
        $visitor=$request->cookie(self::COOKIE);
        if(!is_string($visitor)||!preg_match('/^[a-f0-9]{64}$/D',$visitor))$visitor=bin2hex(random_bytes(32));
        return response()->json(['manualRefresh'=>$this->allowance($visitor)])
            ->header('Cache-Control','no-store')
            ->cookie(self::COOKIE,$visitor,525600,'/',null,$request->isSecure(),true,false,'lax');
    }
    public function refresh(Request $request) {
        if(!$request->isJson() || $request->header('origin')!==$request->getSchemeAndHttpHost() || $request->header('sec-fetch-site')==='cross-site')throw new ApiError('Origine non autorisée.',403);
        $visitor=$request->cookie(self::COOKIE);
        if(!is_string($visitor)||!preg_match('/^[a-f0-9]{64}$/D',$visitor))throw new ApiError('Rechargez la page pour initialiser le compteur.',403);
        if(ApiClient::mode()==='pause')throw new ApiError('Les appels sont temporairement en pause.',409);
        $league=new League;
        if(!$league->client->key())throw new ApiError('Aucune actualisation réelle en démonstration.',409);
        // Un clic accepté compte même si le fournisseur échoue ; le budget API reste distinct.
        $key=$this->key($visitor);
        Cache::lock($key.':lock',5)->block(3,function() use($key) {
            $used=(int)Cache::get($key,0);
            if($used>=self::LIMIT)throw new ApiError('Les 10 actualisations de ce navigateur sont utilisées pour aujourd’hui.',429);
            Cache::put($key,$used+1,now()->utc()->addDay()->startOfDay());
        });
        $fixtures=$league->matches(true);
        $overview=$league->overview(true);
        return response()->json([...$league->metadata(),'fixtures'=>$fixtures,'overview'=>$overview,'manualRefresh'=>$this->allowance($visitor)])
            ->header('Cache-Control','no-store');
    }
}
