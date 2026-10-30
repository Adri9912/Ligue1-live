<?php
namespace App\Http\Controllers;
use App\Football\ApiError;
use App\Football\League;
use Illuminate\Http\Request;
class FootballController extends Controller
{
    public function control(Request $request)
    {
        throw new ApiError('Ce réglage est réservé à la configuration du serveur.',403);
    }
    public function __invoke(Request $request, string $section)
    {
        $league = new League;
        $manual=$request->header('x-l1-manual-refresh')==='1';
        $data=match($section) { 'matches'=>$league->matches($manual), 'overview'=>$league->overview($manual), default=>[] };
        return response()->json([...$league->metadata(),...$data])->header('Cache-Control','no-store')->header('X-Content-Type-Options','nosniff');
    }

    public function detail(Request $r,string $id) { return $this->reply(fn($l,$m)=>$l->detail((int)$id,$m),$r); }
    public function players(Request $r,string $kind) { return $this->reply(fn($l,$m)=>$l->players($kind,$m),$r); }
    public function lineup(Request $r,string $id) {
        $offset=$r->query('offset','0');
        if(!is_string($offset)||!preg_match('/^\d{1,3}$/',$offset))throw new ApiError('Position de recherche invalide.',400);
        return $this->reply(fn($l,$m)=>$l->lineup((int)$id,(int)$offset,$m),$r);
    }
    public function club(Request $r,string $id) { return $this->reply(fn($l,$m)=>(new \App\Football\Clubs($l))->detail((int)$id,$m),$r); }
    public function player(Request $r,string $club,string $id) { return $this->reply(fn($l,$m)=>(new \App\Football\Clubs($l))->player((int)$club,(int)$id,$m),$r); }
    public function retro(Request $r,?string $year=null,?string $section=null) { return $this->reply(fn($l,$m)=>$year===null?(new \App\Football\Retro)->metadata():(new \App\Football\Retro)->get((int)$year,$section,$m),$r); }
    private function reply(callable $action,Request $r) {
        $league=new League;$data=$action($league,$r->header('x-l1-manual-refresh')==='1');
        return response()->json([...$league->metadata(),...$data])->header('Cache-Control','no-store')->header('X-Content-Type-Options','nosniff');
    }
}
