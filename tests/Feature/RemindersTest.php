<?php
namespace Tests\Feature;
use App\Football\Following;
use App\Football\League;
use App\Football\Reminders;
use App\Jobs\SendMatchReminder;
use App\Mail\ReminderMail;
use App\Mail\ReminderMailer;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;
class RemindersTest extends TestCase {
    use RefreshDatabase;
    protected function beforeRefreshingDatabase():void {
        if(!app()->environment('testing')||config('database.default')!=='mysql'||config('database.connections.mysql.url')||config('database.connections.mysql.database')!=='ligue1_live_test')throw new \LogicException('Base dédiée obligatoire.');
    }
    protected function setUp():void {
        parent::setUp();$this->travelTo(now()->utc()->setDate(2026,11,3)->setTime(16,0));
        config(['accounts.enabled'=>true,'reminders.enabled'=>true,'football.provider'=>'highlightly','football.highlightly_key'=>'fake','football.season'=>2026,'football.refresh_mode'=>'auto','football.spacing_ms'=>0,'queue.connections.database.connection'=>null]);
        Cache::flush();Http::preventStrayRequests();$this->fakeCalendar();
    }
    protected function tearDown():void { $this->travelBack();parent::tearDown(); }
    private function fakeCalendar(string $date='2026-11-03T19:00:00Z',string $state='Not started'):void {
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
        $row=['id'=>100,'date'=>$date,'round'=>'9','league'=>['id'=>52695,'season'=>2026],'state'=>['description'=>$state],'homeTeam'=>['id'=>1,'name'=>'Paris Saint Germain'],'awayTeam'=>['id'=>2,'name'=>'Lyon']];
        Http::fake(['*/matches*'=>Http::response(['data'=>[$row],'pagination'=>['totalCount'=>1]])]);
    }
    private function subscriber(bool $both=false):User {
        $user=User::create(['email'=>'test@example.test','password'=>'Une phrase de test 2026!']);$user->markEmailAsVerified();
        foreach($both?[1,2]:[1] as $id) {
            $team=Following::rememberTeam(['id'=>$id,'name'=>$id===1?'PSG':'Lyon','fullName'=>$id===1?'Paris Saint-Germain':'Lyon','code'=>$id===1?'PSG':'LYO'],new League);
            $user->followedTeams()->attach($team->id);
            DB::table('notification_preferences')->insert(['user_id'=>$user->id,'team_id'=>$team->id,'reminders_enabled'=>true,'enabled_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        }
        return $user;
    }
    private function due():NotificationDelivery { $this->travel(1)->hours();$this->assertSame(1,app(Reminders::class)->collect());return NotificationDelivery::firstOrFail(); }
    private function clearFootballResponses():void {
        // Dans le cache array de test uniquement ; aucune remise à zéro de quota de production.
        Cache::flush();
    }
    public function test_no_subscriber_or_disabled_service_never_calls_provider():void {
        $this->assertSame(0,app(Reminders::class)->collect());Http::assertNothingSent();
        $this->subscriber();config(['reminders.enabled'=>false]);$this->assertSame(0,app(Reminders::class)->collect());Http::assertNothingSent();
    }
    public function test_only_h_minus_two_prepares_one_delivery_and_one_job_even_for_two_clubs():void {
        $this->subscriber(true);$this->assertSame(0,app(Reminders::class)->collect());
        $this->due();$this->assertSame(0,app(Reminders::class)->collect());
        $this->assertDatabaseCount('notification_deliveries',1);$this->assertDatabaseCount('jobs',1);
        $this->assertSame('pending',NotificationDelivery::first()->status);
    }
    public function test_unverified_disabled_or_late_consent_do_not_receive_reminders():void {
        $user=$this->subscriber();$user->forceFill(['email_verified_at'=>null])->save();$this->travel(1)->hours();
        $this->assertSame(0,app(Reminders::class)->collect());
        $user->forceFill(['email_verified_at'=>now()->subHours(2),'active'=>false])->save();$this->assertSame(0,app(Reminders::class)->collect());
        $user->forceFill(['active'=>true])->save();DB::table('notification_preferences')->update(['enabled_at'=>now()->addSecond()]);
        $this->assertSame(0,app(Reminders::class)->collect());$this->assertDatabaseCount('jobs',0);
    }
    public function test_paused_mode_and_late_window_never_prepare_mail():void {
        $this->subscriber();$this->travel(1)->hours();config(['football.refresh_mode'=>'pause']);
        $this->assertSame(0,app(Reminders::class)->collect());Http::assertNothingSent();
        config(['football.refresh_mode'=>'auto']);(new League)->matches();$this->travel(11)->minutes();
        Http::fake(['*'=>Http::response([],429)]);$this->assertSame(0,app(Reminders::class)->collect());$this->assertDatabaseCount('jobs',0);
    }
    public function test_stale_calendar_is_not_used_when_the_provider_fails_at_reminder_time():void {
        $this->subscriber();(new League)->matches();$this->travel(1)->hours();
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['*'=>Http::response([],429)]);
        $this->assertSame(0,app(Reminders::class)->collect());$this->assertDatabaseCount('jobs',0);Http::assertSentCount(1);
    }
    public function test_exhausted_shared_budget_prevents_refresh_and_reminder_from_old_calendar():void {
        $this->subscriber();(new League)->matches();$this->travel(1)->hours();
        Cache::forever('football:quota:highlightly',['day'=>now()->utc()->format('Y-m-d'),'used'=>config('football.daily_limit')]);
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
        $this->assertSame(0,app(Reminders::class)->collect());$this->assertDatabaseCount('jobs',0);Http::assertNothingSent();
    }
    public function test_worker_rechecks_unsubscribe_before_connecting():void {
        $this->subscriber();$delivery=$this->due();DB::table('notification_preferences')->update(['reminders_enabled'=>false]);
        $sender=Mockery::mock(ReminderMailer::class);$sender->shouldNotReceive('connect');$sender->shouldNotReceive('send');
        (new SendMatchReminder($delivery->id))->handle($sender);$this->assertSame('cancelled',$delivery->fresh()->status);
    }
    public function test_worker_rechecks_changes_during_smtp_connection():void {
        $this->subscriber();$delivery=$this->due();$sender=Mockery::mock(ReminderMailer::class);
        $sender->shouldReceive('connect')->once()->andReturnUsing(fn()=>DB::table('notification_preferences')->update(['reminders_enabled'=>false]));$sender->shouldNotReceive('send');
        (new SendMatchReminder($delivery->id))->handle($sender);$this->assertSame('cancelled',$delivery->fresh()->status);
    }
    public function test_sent_job_is_not_sent_twice():void {
        $this->subscriber();$delivery=$this->due();$sender=Mockery::mock(ReminderMailer::class);
        $sender->shouldReceive('connect')->once();$sender->shouldReceive('send')->once()->with(Mockery::on(fn($d)=>$d->user->email==='test@example.test'));
        $job=new SendMatchReminder($delivery->id);$job->handle($sender);$job->handle($sender);
        $this->assertSame('sent',$delivery->fresh()->status);$this->assertNotNull($delivery->fresh()->sent_at);
    }
    public function test_connection_failure_retries_at_most_three_times_without_storing_secret_diagnostic():void {
        $this->subscriber();$delivery=$this->due();$sender=Mockery::mock(ReminderMailer::class);
        $sender->shouldReceive('connect')->times(3)->andThrow(new \RuntimeException('private-smtp-diagnostic'));$sender->shouldNotReceive('send');
        $job=new SendMatchReminder($delivery->id);for($i=0;$i<4;$i++)$job->handle($sender);
        $this->assertSame('failed',$delivery->fresh()->status);$this->assertSame(3,$delivery->fresh()->attempts);
        $this->assertSame('connection_or_data',$delivery->fresh()->last_error);
    }
    public function test_uncertain_smtp_delivery_is_never_automatically_resent():void {
        $this->subscriber();$delivery=$this->due();$sender=Mockery::mock(ReminderMailer::class);
        $sender->shouldReceive('connect')->once();$sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('private-smtp-diagnostic'));
        $job=new SendMatchReminder($delivery->id);$job->handle($sender);$job->handle($sender);
        $this->assertSame('uncertain',$delivery->fresh()->status);$this->assertSame('delivery_uncertain',$delivery->fresh()->last_error);
    }
    public function test_postponement_cancellation_and_email_change_cancel_queued_reminder():void {
        $user=$this->subscriber();$delivery=$this->due();$this->fakeCalendar('2026-11-04T19:00:00Z','Postponed');$this->clearFootballResponses();
        $sender=Mockery::mock(ReminderMailer::class);$sender->shouldNotReceive('connect');$sender->shouldNotReceive('send');
        (new SendMatchReminder($delivery->id))->handle($sender);$this->assertSame('cancelled',$delivery->fresh()->status);
    }
    public function test_changed_address_or_deleted_account_does_not_receive_queued_reminder():void {
        $user=$this->subscriber();$delivery=$this->due();$user->forceFill(['email'=>'new@example.test','email_verified_at'=>null])->save();
        $sender=Mockery::mock(ReminderMailer::class);$sender->shouldNotReceive('send');$sender->shouldNotReceive('connect');
        (new SendMatchReminder($delivery->id))->handle($sender);$this->assertSame('cancelled',$delivery->fresh()->status);
        $user->delete();(new SendMatchReminder($delivery->id))->handle($sender);$this->assertDatabaseCount('notification_deliveries',0);
    }
    public function test_preferences_are_owned_idempotent_and_can_wait_for_verification():void {
        $user=$this->subscriber();$team=$user->followedTeams()->first();DB::table('notification_preferences')->delete();
        $this->getJson('/api/account/following')->assertUnauthorized();
        $this->actingAs($user)->putJson('/api/account/following/'.$team->id.'/reminders',['enabled'=>true,'user_id'=>999])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',true);
        $at=DB::table('notification_preferences')->value('enabled_at');$this->travel(5)->minutes();
        $this->putJson('/api/account/following/'.$team->id.'/reminders',['enabled'=>true])->assertOk();$this->assertSame($at,DB::table('notification_preferences')->value('enabled_at'));
        $this->putJson('/api/account/following/999/reminders',['enabled'=>true])->assertNotFound();
        $user->forceFill(['email_verified_at'=>null])->save();$this->putJson('/api/account/following/'.$team->id.'/reminders',['enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',true);
        $this->putJson('/api/account/following/'.$team->id.'/reminders',['enabled'=>false])->assertOk();
    }
    public function test_signed_unsubscribe_requires_confirmation_and_does_not_touch_other_account():void {
        $user=$this->subscriber();$other=User::create(['email'=>'other@example.test','password'=>'Une phrase de test 2026!']);$team=$user->followedTeams()->first();$other->followedTeams()->attach($team->id);
        DB::table('notification_preferences')->insert(['user_id'=>$other->id,'team_id'=>$team->id,'reminders_enabled'=>true,'enabled_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $url=URL::temporarySignedRoute('reminders.unsubscribe',now()->addDay(),['user'=>$user->id]);
        $this->get($url)->assertOk()->assertSee('Désactiver les rappels');$this->assertDatabaseHas('notification_preferences',['user_id'=>$user->id,'reminders_enabled'=>true]);
        $this->get($url.'&tampered=1')->assertForbidden();$this->post($url)->assertOk()->assertSee('Rappels désactivés');
        $this->assertDatabaseHas('notification_preferences',['user_id'=>$user->id,'reminders_enabled'=>false]);$this->assertDatabaseHas('notification_preferences',['user_id'=>$other->id,'reminders_enabled'=>true]);
    }
    public function test_native_database_queue_processes_one_reminder():void {
        $this->subscriber();$delivery=$this->due();$sender=Mockery::mock(ReminderMailer::class);
        $sender->shouldReceive('connect')->once();$sender->shouldReceive('send')->once();$this->app->instance(ReminderMailer::class,$sender);
        $this->artisan('queue:work',['connection'=>'database','--queue'=>'mail','--once'=>true,'--stop-when-empty'=>true])->assertSuccessful();
        $this->assertDatabaseCount('jobs',0);$this->assertSame('sent',$delivery->fresh()->status);
    }
    public function test_changed_schedule_can_be_prepared_again_only_if_not_already_sent():void {
        $this->subscriber();$delivery=$this->due();$this->fakeCalendar('2026-11-03T19:05:00Z');$this->clearFootballResponses();
        $this->assertSame(0,app(Reminders::class)->collect());
        $sender=Mockery::mock(ReminderMailer::class);$sender->shouldNotReceive('connect');$sender->shouldNotReceive('send');
        (new SendMatchReminder($delivery->id))->handle($sender);$this->assertSame('cancelled',$delivery->fresh()->status);
        $this->travel(5)->minutes();$this->assertSame(1,app(Reminders::class)->collect());$this->assertDatabaseCount('notification_deliveries',1);
        $delivery->refresh()->update(['status'=>'sent','sent_at'=>now()]);$this->assertSame(0,app(Reminders::class)->collect());
    }
    public function test_expired_unsubscribe_link_is_refused():void {
        $user=$this->subscriber();$url=URL::temporarySignedRoute('reminders.unsubscribe',now()->subMinute(),['user'=>$user->id]);
        $this->get($url)->assertForbidden();$this->assertDatabaseHas('notification_preferences',['user_id'=>$user->id,'reminders_enabled'=>true]);
    }
    public function test_email_renders_paris_time_and_unsubscribe_without_sending():void {
        $this->subscriber();$delivery=$this->due();$html=(new ReminderMail($delivery->load('fixture')))->render();
        $this->assertStringContainsString('20 h 00',$html);$this->assertStringContainsString('heure de Paris',$html);$this->assertStringContainsString('Désactiver tous mes rappels',$html);$this->assertStringContainsString('/matchs/100',$html);
    }
}
