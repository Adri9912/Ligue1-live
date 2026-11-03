<?php
namespace App\Football;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
class Following {
    public static function snapshot(?User $user): array {
        $league=new League;
        $provider=$league->client->provider;
        $preferences=$user?\Illuminate\Support\Facades\DB::table('notification_preferences')->where('user_id',$user->id)->pluck('reminders_enabled','team_id'):collect();
        return ['remindersAvailable'=>(bool)config('reminders.enabled'),'primaryTeamId'=>$user?->primary_team_id,'source'=>$provider,'teams'=>$user?$user->followedTeams()->orderBy('teams.name')->get()->map(fn($t)=>[
            'id'=>$t->id,'providerId'=>$provider==='highlightly'?$t->highlightly_id:$t->api_football_id,
            'apiFootballId'=>$t->api_football_id,'name'=>$t->name,'fullName'=>$t->full_name,'code'=>$t->code,'logo'=>$t->logo,'lastSeenSeason'=>$t->last_seen_season,'remindersEnabled'=>(bool)($preferences[$t->id]??false),
        ])->all():[]];
    }
    public static function attach(User $user,Team $team,?bool $reminders):void {
        $changes=$user->followedTeams()->syncWithoutDetaching([$team->id]);
        // Une préférence n'est initialisée que pour un nouveau suivi et un choix transmis.
        if($changes['attached']&&config('reminders.enabled')&&$reminders!==null) {
            \Illuminate\Support\Facades\DB::table('notification_preferences')->insertOrIgnore([
                'user_id'=>$user->id,'team_id'=>$team->id,'reminders_enabled'=>$reminders,
                'enabled_at'=>$reminders?now():null,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
    public static function currentTeam(int $id): Team {
        $league=new League;
        if(!$league->client->key())throw new ApiError('Les favoris enregistrés nécessitent les données réelles de Ligue 1.',409);
        $overview=$league->overview();
        $team=collect($overview['teams'])->firstWhere('id',$id);
        if(!$team)throw new ApiError('Choisissez un club de Ligue 1 de la saison actuelle.',422);
        return self::rememberTeam($team,$league);
    }
    public static function rememberTeam(array $team,League $league): Team {
        $id=$team['id'];
        $column=$league->client->provider==='highlightly'?'highlightly_id':'api_football_id';
        $apiId=$column==='api_football_id'?$id:Clubs::cachedApiFootballId($team);
        $identity=preg_replace('/[^a-z0-9]/','',Str::lower(Str::ascii($team['fullName']??$team['name'])));
        $record=Team::where($column,$id)->first()??($apiId?Team::where('api_football_id',$apiId)->first():null)??Team::where('identity_key',$identity)->first()??Team::firstOrCreate(['identity_key'=>$identity],['name'=>$team['name'],'full_name'=>$team['fullName']??$team['name'],'code'=>$team['code'],'logo'=>$team['logo']??null,'last_seen_season'=>$league->season,$column=>$id]);
        $record->fill(['identity_key'=>$identity,'name'=>$team['name'],'full_name'=>$team['fullName']??$team['name'],'code'=>$team['code'],'logo'=>$team['logo']??null,'last_seen_season'=>$league->season,$column=>$id]);
        if($apiId)$record->api_football_id=$apiId;
        $record->save();return $record;
    }
}
