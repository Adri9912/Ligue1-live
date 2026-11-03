<?php
namespace App\Http\Controllers;
use App\Football\Following;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class FollowingController extends Controller {
    private function reply(Request $request) {
        return response()->json(['following'=>Following::snapshot($request->user()->fresh())])->header('Cache-Control','no-store');
    }
    public function index(Request $request) { return $this->reply($request); }
    public function follow(Request $request) {
        $data=$request->validate(['team_id'=>['required','integer','min:1'],'primary'=>['sometimes','boolean'],'reminders_enabled'=>['sometimes','boolean']]);
        $team=Following::currentTeam($data['team_id']);
        DB::transaction(function() use($request,$data,$team) {
            $user=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Following::attach($user,$team,$data['reminders_enabled']??null);
            if(($data['primary']??false)||!$user->primary_team_id)$user->forceFill(['primary_team_id'=>$team->id])->save();
        });
        return $this->reply($request);
    }
    public function primary(Request $request) {
        $data=$request->validate(['team_id'=>['present','nullable','integer','min:1'],'reminders_enabled'=>['sometimes','boolean']]);
        $team=$data['team_id']===null?null:Following::currentTeam($data['team_id']);
        DB::transaction(function() use($request,$data,$team) {
            $user=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if($team)Following::attach($user,$team,$data['reminders_enabled']??null);
            $user->forceFill(['primary_team_id'=>$team?->id])->save();
        });
        return $this->reply($request);
    }
    public function unfollow(Request $request,string $id) {
        DB::transaction(function() use($request,$id) {
            $user=User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->followedTeams()->where('teams.id',$id)->exists(),404,'Ce club ne fait pas partie de votre suivi.');
            if($user->primary_team_id===(int)$id)$user->forceFill(['primary_team_id'=>null])->save();
            $user->followedTeams()->detach((int)$id);
        });
        return $this->reply($request);
    }
}
