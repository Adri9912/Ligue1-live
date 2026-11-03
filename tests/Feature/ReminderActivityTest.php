<?php
namespace Tests\Feature;
use App\Football\ReminderActivity;
use App\Football\Reminders;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class ReminderActivityTest extends TestCase {
    use RefreshDatabase;
    protected function beforeRefreshingDatabase():void {
        if(!app()->environment('testing')||config('database.default')!=='mysql'||config('database.connections.mysql.url')||config('database.connections.mysql.database')!=='ligue1_live_test')throw new \LogicException('Base dédiée obligatoire.');
    }
    protected function setUp():void {
        parent::setUp();config(['accounts.enabled'=>true,'reminders.enabled'=>true,'football.refresh_mode'=>'auto']);
        $this->travelTo(now()->utc()->setDate(2026,11,3)->setTime(12,0));Cache::flush();Http::preventStrayRequests();Mail::fake();
    }
    protected function tearDown():void { $this->travelBack();parent::tearDown(); }
    private function task(string $name='prepare'):array { return collect(ReminderActivity::snapshot()['tasks'])->firstWhere('task',$name); }
    private function worker(int $exit=0,bool $crash=false):Command {
        $worker=new class extends Command {
            protected $signature='queue:work {connection?} {--queue=} {--stop-when-empty} {--max-time=} {--max-jobs=} {--timeout=}';
            public int $exit=0;public bool $crash=false;public array $seen=[];
            public function handle():int {
                $this->seen=['connection'=>$this->argument('connection'),'queue'=>$this->option('queue'),'maxTime'=>$this->option('max-time'),'stop'=>$this->option('stop-when-empty')];
                if($this->crash)throw new \RuntimeException('dummy-private-worker-diagnostic');return $this->exit;
            }
        };
        $worker->exit=$exit;$worker->crash=$crash;$this->app->make(Kernel::class)->registerCommand($worker);return $worker;
    }
    public function test_no_run_is_explicit_and_deliberate_pause_or_disabling_is_not_an_outage():void {
        $this->assertSame('never',$this->task()['state']);$this->assertNull($this->task()['startedAt']);
        config(['football.refresh_mode'=>'pause']);$this->assertSame('paused',$this->task()['state']);
        config(['reminders.enabled'=>false]);$this->assertSame('disabled',$this->task()['state']);
        Http::assertNothingSent();Mail::assertNothingSent();
    }
    public function test_running_success_zero_prepared_and_overdue_detection_have_precise_boundaries():void {
        ReminderActivity::start('prepare');$this->assertSame('running',$this->task()['state']);
        ReminderActivity::finish('prepare','success',0);$record=$this->task();$this->assertSame('healthy',$record['state']);$this->assertSame(0,$record['prepared']);
        $this->assertSame($record['finishedAt'],$record['lastSuccessAt']);
        $this->travel(5)->minutes();$this->assertSame('healthy',$this->task()['state']);$this->travel(1)->seconds();$this->assertSame('late',$this->task()['state']);
        ReminderActivity::start('mail');$this->travel(6)->minutes();$this->assertSame('late',$this->task('mail')['state']);$this->assertNull($this->task('mail')['finishedAt']);
        ReminderActivity::finish('mail','success');$this->assertSame('healthy',$this->task('mail')['state']);
    }
    public function test_real_prepare_command_records_completion_without_sending_any_message():void {
        $this->mock(Reminders::class)->shouldReceive('collect')->once()->andReturn(3);
        $this->artisan('football:reminders')->assertExitCode(0);
        $this->assertSame('healthy',$this->task()['state']);$this->assertSame(3,$this->task()['prepared']);
        $this->assertSame('never',$this->task('mail')['state']);Http::assertNothingSent();Mail::assertNothingSent();
    }
    public function test_prepare_failure_is_sanitized_and_keeps_previous_success():void {
        ReminderActivity::start('prepare');ReminderActivity::finish('prepare','success',2);$success=$this->task()['lastSuccessAt'];$this->travel(1)->minutes();
        $this->mock(Reminders::class)->shouldReceive('collect')->once()->andThrow(new \RuntimeException('dummy-private-api-key'));
        $this->artisan('football:reminders')->doesntExpectOutputToContain('dummy-private-api-key')->assertExitCode(1);
        $this->assertSame('failed',$this->task()['state']);$this->assertSame($success,$this->task()['lastSuccessAt']);
        $this->assertStringNotContainsString('dummy-private',json_encode(ReminderActivity::snapshot()));
    }
    public function test_worker_wrapper_records_real_empty_queue_success():void {
        $this->artisan('football:send-reminders')->assertExitCode(0);
        $this->assertSame('healthy',$this->task('mail')['state']);$this->assertSame('never',$this->task()['state']);Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_worker_returning_failure_and_throwing_exception_are_both_recorded():void {
        $worker=$this->worker(1);$this->artisan('football:send-reminders')->assertExitCode(1);
        $this->assertSame('failed',$this->task('mail')['state']);$this->assertSame('database',$worker->seen['connection']);$this->assertSame('mail',$worker->seen['queue']);
        $this->assertSame(45,(int)$worker->seen['maxTime']);$this->assertTrue($worker->seen['stop']);
        $worker->crash=true;$this->artisan('football:send-reminders')->doesntExpectOutputToContain('dummy-private-worker-diagnostic')->assertExitCode(1);
        $this->assertSame('failed',$this->task('mail')['state']);$this->assertStringNotContainsString('dummy-private',json_encode(ReminderActivity::snapshot()));
    }
    public function test_paused_and_disabled_commands_never_start_collect_or_worker_and_resume_waits_for_new_execution():void {
        $worker=$this->worker();$this->mock(Reminders::class)->shouldNotReceive('collect');
        config(['football.refresh_mode'=>'pause']);$this->artisan('football:reminders')->assertExitCode(0);$this->artisan('football:send-reminders')->assertExitCode(0);
        $this->assertSame('skipped',$this->task()['result']);$this->assertSame('paused',$this->task('mail')['state']);$this->assertSame([],$worker->seen);
        config(['football.refresh_mode'=>'auto']);$this->assertSame('waiting',$this->task()['state']);
        config(['reminders.enabled'=>false]);$this->artisan('football:reminders')->assertExitCode(0);$this->artisan('football:send-reminders')->assertExitCode(0);
        $this->assertSame('disabled',$this->task()['state']);$this->assertSame([],$worker->seen);Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_reading_status_never_updates_the_last_execution():void {
        ReminderActivity::start('prepare');ReminderActivity::finish('prepare','success',0);$record=$this->task();$checked=ReminderActivity::snapshot()['checkedAt'];
        $this->travel(2)->minutes();$this->assertSame($record,$this->task());$this->assertNotSame($checked,ReminderActivity::snapshot()['checkedAt']);Http::assertNothingSent();
    }
}
