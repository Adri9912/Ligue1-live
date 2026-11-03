<?php
namespace App\Http\Controllers;
use App\Football\ApiClient;
use App\Models\User;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class AdminController extends Controller {
    private function reply(array $data) { return response()->json($data)->header('Cache-Control','no-store'); }
    public function overview() {
        $budgets=[];foreach(['highlightly','api-football','balldontlie'] as $provider) {
            $client=new ApiClient($provider);$budgets[]=['provider'=>$provider,'configured'=>$client->key()!=='','budget'=>$client->budget()];
        }
        return $this->reply(['stats'=>[
            'users'=>User::count(),'active'=>User::where('active',true)->count(),'verified'=>User::whereNotNull('email_verified_at')->count(),
            'subscribers'=>DB::table('notification_preferences')->where('reminders_enabled',true)->distinct()->count('user_id'),
            'deliveries'=>NotificationDelivery::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status'),
        ],'refreshMode'=>ApiClient::mode(),'remindersEnabled'=>(bool)config('reminders.enabled'),'budgets'=>$budgets,'reminderActivity'=>\App\Football\ReminderActivity::snapshot()]);
    }
    public function users(Request $request) {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'page'=>['nullable','integer','min:1','max:100000']]);
        $users=User::select(['id','email','role','active','email_verified_at','created_at'])->withCount('followedTeams')->orderByDesc('id');
        if(($q=trim($data['q']??''))!=='')$users->whereRaw("email LIKE ? ESCAPE '!'",['%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%']);
        return $this->reply(['users'=>$users->paginate(20)]);
    }
    public function status(Request $request,string $id) {
        $data=$request->validate(['active'=>['required','boolean'],'current_password'=>['required','current_password']]);
        DB::transaction(function()use($request,$id,$data) {
            $rows=User::whereIn('id',[$request->user()->id,$id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor=$rows->get($request->user()->id);$target=$rows->get((int)$id);
            abort_unless($actor&&$actor->active&&$actor->role==='admin'&&$actor->hasVerifiedEmail(),403);
            abort_unless($target,404);abort_if($target->role==='admin',422,'Les comptes administrateurs ne peuvent pas être désactivés ici.');
            $target->forceFill(['active'=>$data['active']])->save();
            if(!$data['active']) {
                $target->forceFill(['remember_token'=>Str::random(60)])->save();
                DB::table('sessions')->where('user_id',$target->id)->delete();DB::table('password_reset_tokens')->where('email',$target->email)->delete();
                NotificationDelivery::where('user_id',$target->id)->where('status','pending')->update(['status'=>'cancelled','last_error'=>'account_disabled']);
            }
        });
        return $this->reply(['message'=>$data['active']?'Compte réactivé.':'Compte désactivé et connexions fermées.']);
    }
    public function deliveries(Request $request) {
        $data=$request->validate(['status'=>['nullable','in:pending,sending,sent,failed,uncertain,cancelled'],'page'=>['nullable','integer','min:1','max:100000']]);
        $rows=NotificationDelivery::query()->with(['user:id,email','fixture:id,fixture,kickoff_at'])->orderByDesc('id');
        if(!empty($data['status']))$rows->where('status',$data['status']);
        $page=$rows->paginate(20);$page->through(fn($d)=>[
            'id'=>$d->id,'email'=>$d->user?->email,'match'=>$d->fixture?->fixture['home']['name'].' – '.$d->fixture?->fixture['away']['name'],
            'status'=>$d->status,'attempts'=>$d->attempts,'kickoffAt'=>$d->expected_kickoff_at,'sentAt'=>$d->sent_at,'createdAt'=>$d->created_at,
        ]);
        return $this->reply(['deliveries'=>$page]);
    }
}
