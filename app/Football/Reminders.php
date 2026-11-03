<?php
namespace App\Football;
use App\Jobs\SendMatchReminder;
use App\Models\FootballMatch;
use App\Models\NotificationDelivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
class Reminders {
    public static function available():bool { return config('accounts.enabled')&&config('reminders.enabled'); }
    public static function inWindow(CarbonImmutable $kickoff):bool {
        $due=$kickoff->subHours(2);return now()->gte($due)&&now()->lt($due->addMinutes(10));
    }
    public static function eligible(User $user,FootballMatch $match):bool {
        if(!$user->active||!$user->hasVerifiedEmail()||$user->email_verified_at->gt($match->kickoff_at->subHours(2)))return false;
        return DB::table('notification_preferences')->where('user_id',$user->id)->whereIn('team_id',[$match->home_team_id,$match->away_team_id])
            ->where('reminders_enabled',true)->where('enabled_at','<=',$match->kickoff_at->subHours(2))->exists();
    }
    public function collect():int {
        if(!self::available()||ApiClient::mode()==='pause')return 0;
        $league=new League;if(!$league->client->key())return 0;
        $column=$league->client->provider==='highlightly'?'highlightly_id':'api_football_id';
        $teamIds=DB::table('notification_preferences')->join('users','users.id','=','notification_preferences.user_id')->join('teams','teams.id','=','notification_preferences.team_id')
            ->where('reminders_enabled',true)->where('users.active',true)->whereNotNull('users.email_verified_at')->pluck('teams.'.$column)->filter()->map(fn($id)=>(int)$id)->all();
        if(!$teamIds)return 0;
        $data=$league->matches();
        $due=fn($m)=>$m['status']==='NS'&&self::inWindow(CarbonImmutable::parse($m['date']))&&(in_array($m['home']['id'],$teamIds,true)||in_array($m['away']['id'],$teamIds,true));
        // Une vérification partagée à l'approche des rappels, jamais une par destinataire.
        if(collect($data['matches'])->contains($due)&&ApiClient::mode()==='auto')$data=$league->matches(true);
        if($data['stale']||Schedule::now()-$data['fetchedAt']>Schedule::TEN_MINUTES)return 0;
        $count=0;
        foreach($data['matches'] as $fixture) {
            $existing=FootballMatch::where('provider',$league->client->provider)->where('external_id',$fixture['id'])->where('season',$league->season)->first();
            if(!$due($fixture)&&!$existing)continue;
            $home=Following::rememberTeam($fixture['home'],$league);$away=Following::rememberTeam($fixture['away'],$league);
            $game=FootballMatch::updateOrCreate(['season'=>$league->season,'home_team_id'=>$home->id,'away_team_id'=>$away->id],[
                'provider'=>$league->client->provider,'external_id'=>$fixture['id'],'kickoff_at'=>CarbonImmutable::parse($fixture['date'])->utc(),'status'=>$fixture['status'],'fixture'=>$fixture,
                'fetched_at'=>CarbonImmutable::createFromTimestampMs($data['fetchedAt'])->utc(),
            ]);
            if(!$due($fixture))continue;
            $recipients=DB::table('notification_preferences')->whereIn('team_id',[$home->id,$away->id])->where('reminders_enabled',true)->distinct()->pluck('user_id');
            foreach($recipients as $userId)$count+=DB::transaction(function()use($userId,$game) {
                $user=User::whereKey($userId)->lockForUpdate()->first();if(!$user||!self::eligible($user,$game))return 0;
                $delivery=NotificationDelivery::firstOrCreate(['user_id'=>$userId,'match_id'=>$game->id,'type'=>'reminder'],['expected_kickoff_at'=>$game->kickoff_at]);
                if($delivery->status==='cancelled'&&!$delivery->expected_kickoff_at->eq($game->kickoff_at))$delivery->fill(['status'=>'pending','queued_at'=>null,'attempts'=>0,'expected_kickoff_at'=>$game->kickoff_at]);
                if($delivery->status!=='pending'||$delivery->queued_at)return 0;
                $delivery->queued_at=now();$delivery->save();
                // Le job et la livraison sont insérés dans la même transaction MySQL.
                Bus::dispatch((new SendMatchReminder($delivery->id))->onConnection('database')->onQueue('mail')->beforeCommit());
                return 1;
            });
        }
        return $count;
    }
    public static function canSend(NotificationDelivery $delivery):bool {
        $delivery->load(['user','fixture']);$game=$delivery->fixture;
        if(!self::available()||!$game||!$delivery->user||!self::inWindow($game->kickoff_at)||!$delivery->expected_kickoff_at->eq($game->kickoff_at)||!self::eligible($delivery->user,$game))return false;
        $league=new League;
        if(!$league->client->key()||$game->provider!==$league->client->provider||$game->season!==$league->season)return false;
        $data=$league->matches();
        $current=collect($data['matches'])->firstWhere('id',$game->external_id);
        return !$data['stale']&&Schedule::now()-$data['fetchedAt']<=Schedule::TEN_MINUTES&&$current&&$current['status']==='NS'&&CarbonImmutable::parse($current['date'])->eq($game->kickoff_at);
    }
}
