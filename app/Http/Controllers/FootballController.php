<?php
namespace App\Http\Controllers;
use App\Football\ApiError;
use App\Football\League;
use Illuminate\Http\Request;
class FootballController extends Controller
{
    public function control(Request $request)
    {
        if ($request->header('sec-fetch-site') === 'cross-site') throw new ApiError('Origine non autorisée.',403);
        if ($origin=$request->header('origin')) {
            $parts=parse_url($origin);
            $host=($parts['host']??'').(isset($parts['port'])?':'.$parts['port']:'');
            if (!in_array($parts['scheme']??'', ['http','https'], true) || $host!==$request->getHttpHost()) throw new ApiError('Origine non autorisée.',403);
        }
        if (explode(';',$request->header('content-type',''))[0]!=='application/json') throw new ApiError('Un contenu JSON est requis.',415);
        if (strlen($request->getContent())>1024) throw new ApiError('Réglage trop volumineux.',413);
        $body=json_decode($request->getContent(),true);
        if (json_last_error()!==JSON_ERROR_NONE) throw new ApiError('Réglage JSON invalide.',400);
        $mode=is_array($body)?($body['mode']??null):null;
        if (!in_array($mode,['auto','manual','pause'],true)) throw new ApiError('Choisissez Auto, Manuel ou Pause.',400);
        \Illuminate\Support\Facades\Cache::forever('football:mode',$mode);
        return response()->json((new League)->metadata())->header('Cache-Control','no-store');
    }
    public function __invoke(Request $request, string $section)
    {
        $league = new League;
        if ($section !== 'status' && !$league->client->key()) throw new ApiError('Le mode démonstration Laravel est en cours de migration.',503);
        $manual=$request->header('x-l1-manual-refresh')==='1';
        $data=match($section) { 'matches'=>$league->matches($manual), 'overview'=>$league->overview($manual), default=>[] };
        return response()->json([...$league->metadata(),...$data])->header('Cache-Control','no-store')->header('X-Content-Type-Options','nosniff');
    }
}
