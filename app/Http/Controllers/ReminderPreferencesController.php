<?php
namespace App\Http\Controllers;
use App\Football\Following;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReminderPreferencesController extends Controller {
    public function update(Request $request,string $id) {
        abort_unless(config('reminders.enabled'),503,'Les rappels ne sont pas encore ouverts.');
        $data=$request->validate(['enabled'=>['required','boolean']]);$user=$request->user();
        abort_if($data['enabled']&&!$user->hasVerifiedEmail(),403,'Vérifiez votre adresse e-mail avant d’activer les rappels.');
        DB::transaction(function()use($user,$id,$data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->followedTeams()->where('teams.id',$id)->exists(),404,'Ce club ne fait pas partie de votre suivi.');
            $query=DB::table('notification_preferences')->where('user_id',$user->id)->where('team_id',$id);$current=$query->first();
            DB::table('notification_preferences')->updateOrInsert(['user_id'=>$user->id,'team_id'=>(int)$id],[
                'reminders_enabled'=>$data['enabled'],'enabled_at'=>$data['enabled']?($current?->enabled_at??now()):null,
                'created_at'=>$current?->created_at??now(),'updated_at'=>now(),
            ]);
        });
        return response()->json(['following'=>Following::snapshot($user->fresh())])->header('Cache-Control','no-store');
    }
    public function unsubscribe(Request $request,string $user) {
        User::findOrFail($user);
        if($request->isMethod('post')) {
            DB::table('notification_preferences')->where('user_id',$user)->update(['reminders_enabled'=>false,'enabled_at'=>null,'updated_at'=>now()]);
        }
        return response()->view('mail.unsubscribe',['done'=>$request->isMethod('post')])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
}
