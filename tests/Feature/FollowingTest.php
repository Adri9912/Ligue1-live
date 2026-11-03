<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class FollowingTest extends TestCase {
    use RefreshDatabase;
    protected function beforeRefreshingDatabase(): void {
        if(!app()->environment('testing')||config('database.default')!=='mysql'||config('database.connections.mysql.url')||config('database.connections.mysql.database')!=='ligue1_live_test')throw new \LogicException('Base de test dédiée obligatoire.');
    }
    protected function setUp(): void {
        parent::setUp();config(['accounts.enabled'=>true,'football.provider'=>'highlightly','football.highlightly_key'=>'fake','football.api_football_key'=>'fake','football.season'=>2026,'football.spacing_ms'=>0]);Cache::flush();Http::preventStrayRequests();
        $rows=[];foreach([[73119,'Paris Saint Germain'],[68864,'Lyon'],[66311,'Angers']] as $i=>[$id,$name])$rows[]=['position'=>$i+1,'team'=>['id'=>$id,'name'=>$name],'points'=>5-$i,'total'=>['games'=>2,'scoredGoals'=>3,'receivedGoals'=>1,'wins'=>1,'draws'=>1,'loses'=>0]];
        Http::fake(['*/standings?*'=>Http::response(['league'=>['id'=>52695,'season'=>2026],'groups'=>[['standings'=>$rows]]])]);
        Cache::forever('football:response:api-football:'.hash('sha256','teams?country=France'),['time'=>now()->getTimestampMs(),'data'=>[['team'=>['id'=>80,'name'=>'Lyon']]]]);
    }
    private function user(string $email='a@example.test'):User { return User::create(['email'=>$email,'password'=>'Une phrase de test 2026!']); }
    public function test_guests_cannot_save_or_read_following(): void {
        $this->getJson('/api/account/following')->assertUnauthorized();
        $this->postJson('/api/account/following',['team_id'=>68864])->assertUnauthorized();
        $this->assertDatabaseCount('user_followed_teams',0);Http::assertNothingSent();
    }
    public function test_first_follow_is_primary_repeated_selection_never_duplicates_and_others_are_followed(): void {
        $u=$this->user();$this->actingAs($u)->postJson('/api/account/following',['team_id'=>68864])->assertOk()->assertJsonCount(1,'following.teams');
        $id=$u->fresh()->primary_team_id;$this->assertNotNull($id);
        $this->postJson('/api/account/following',['team_id'=>68864])->assertOk()->assertJsonPath('following.primaryTeamId',$id);
        $this->postJson('/api/account/following',['team_id'=>73119])->assertOk()->assertJsonCount(2,'following.teams')->assertJsonPath('following.primaryTeamId',$id);
        $this->assertDatabaseCount('user_followed_teams',2);$this->assertDatabaseCount('teams',2);Http::assertSentCount(1);
    }
    public function test_primary_team_is_always_followed_and_cannot_be_overridden_by_user_id(): void {
        $a=$this->user();$b=$this->user('b@example.test');
        $this->actingAs($a)->putJson('/api/account/primary-team',['team_id'=>73119,'user_id'=>$b->id])->assertOk();
        $this->assertDatabaseCount('user_followed_teams',1);$this->assertNull($b->fresh()->primary_team_id);
        $this->putJson('/api/account/primary-team',['team_id'=>68864])->assertOk()->assertJsonCount(2,'following.teams');
        $this->assertSame(68864,Team::find($a->fresh()->primary_team_id)->highlightly_id);
        $this->putJson('/api/account/primary-team',['team_id'=>null])->assertOk()->assertJsonPath('following.primaryTeamId',null)->assertJsonCount(2,'following.teams');
    }
    public function test_unknown_clubs_and_foreign_following_are_refused(): void {
        $a=$this->user();$b=$this->user('b@example.test');
        $this->actingAs($a)->postJson('/api/account/following',['team_id'=>999999])->assertUnprocessable();
        $this->postJson('/api/account/following',['team_id'=>68864])->assertOk();$id=$a->fresh()->primary_team_id;
        $this->actingAs($b)->withSession(['password_hash_web'=>$b->password])->deleteJson('/api/account/following/'.$id)->assertNotFound();
        $this->assertDatabaseHas('user_followed_teams',['user_id'=>$a->id,'team_id'=>$id]);
        $this->getJson('/api/account/following')->assertJsonCount(0,'following.teams');
    }
    public function test_remove_primary_clears_primary_and_only_own_relation(): void {
        $a=$this->user();$b=$this->user('b@example.test');
        $this->actingAs($a)->postJson('/api/account/following',['team_id'=>68864])->assertOk();$id=$a->fresh()->primary_team_id;
        $b->followedTeams()->attach($id);
        $this->deleteJson('/api/account/following/'.$id)->assertOk()->assertJsonPath('following.primaryTeamId',null)->assertJsonCount(0,'following.teams');
        $this->assertDatabaseHas('user_followed_teams',['user_id'=>$b->id,'team_id'=>$id]);$this->assertDatabaseCount('teams',1);
    }
    public function test_stable_team_ids_and_historical_preferences_survive_provider_or_season_change(): void {
        $a=$this->user();$this->actingAs($a)->postJson('/api/account/following',['team_id'=>68864])->assertOk()->assertJsonPath('following.teams.0.apiFootballId',80);
        $id=$a->fresh()->primary_team_id;
        config(['football.provider'=>'api-football','football.season'=>2027]);
        $this->getJson('/api/account/following')->assertOk()->assertJsonPath('following.primaryTeamId',$id)->assertJsonPath('following.teams.0.providerId',80)->assertJsonPath('following.teams.0.lastSeenSeason',2026);
        $this->assertDatabaseCount('user_followed_teams',1);Http::assertSentCount(1);
    }
    public function test_deleting_account_removes_its_follows_and_counts_without_deleting_other_accounts_or_clubs(): void {
        $a=$this->user();$b=$this->user('b@example.test');
        $this->actingAs($a)->postJson('/api/account/following',['team_id'=>68864])->assertOk();
        $id=$a->fresh()->primary_team_id;$b->followedTeams()->attach($id);
        \Illuminate\Support\Facades\DB::table('manual_refresh_counts')->insert(['user_id'=>$a->id,'day'=>'2026-11-02','used'=>3]);
        $this->deleteJson('/api/account',['current_password'=>'Une phrase de test 2026!'])->assertOk();
        $this->assertDatabaseMissing('user_followed_teams',['user_id'=>$a->id]);$this->assertDatabaseMissing('manual_refresh_counts',['user_id'=>$a->id]);
        $this->assertDatabaseHas('user_followed_teams',['user_id'=>$b->id,'team_id'=>$id]);$this->assertDatabaseHas('teams',['id'=>$id]);
    }
    public function test_new_follow_saves_visible_email_choice_but_delivery_waits_for_verified_address():void {
        config(['reminders.enabled'=>true]);$user=$this->user();
        $this->actingAs($user)->postJson('/api/account/following',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',true);
        $teamId=$user->fresh()->primary_team_id;
        $this->assertDatabaseHas('notification_preferences',['user_id'=>$user->id,'team_id'=>$teamId,'reminders_enabled'=>true]);
        $match=new \App\Models\FootballMatch(['home_team_id'=>$teamId,'away_team_id'=>999,'kickoff_at'=>now()->addHours(3)]);
        $this->assertFalse(\App\Football\Reminders::eligible($user->fresh(),$match));
        $user->markEmailAsVerified();$this->assertTrue(\App\Football\Reminders::eligible($user->fresh(),$match));
    }
    public function test_unchecked_choice_is_saved_and_later_primary_changes_do_not_overwrite_it():void {
        config(['reminders.enabled'=>true]);$user=$this->user();
        $this->actingAs($user)->putJson('/api/account/primary-team',['team_id'=>68864,'reminders_enabled'=>false])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',false);
        $this->putJson('/api/account/primary-team',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',false);
        $this->postJson('/api/account/following',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',false);
        $this->assertDatabaseCount('notification_preferences',1);$this->assertDatabaseHas('notification_preferences',['user_id'=>$user->id,'enabled_at'=>null,'reminders_enabled'=>false]);
    }
    public function test_new_primary_can_store_checked_choice_without_duplicate_or_later_implicit_opt_out():void {
        config(['reminders.enabled'=>true]);$user=$this->user();
        $this->actingAs($user)->putJson('/api/account/primary-team',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',true);
        $at=\Illuminate\Support\Facades\DB::table('notification_preferences')->value('enabled_at');$this->travel(5)->minutes();
        $this->postJson('/api/account/following',['team_id'=>68864,'reminders_enabled'=>false])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',true);
        $this->assertDatabaseCount('notification_preferences',1);$this->assertSame($at,\Illuminate\Support\Facades\DB::table('notification_preferences')->value('enabled_at'));
        $this->travelBack();
    }
    public function test_older_follows_with_no_preference_are_not_enrolled_by_switching_primary():void {
        config(['reminders.enabled'=>true]);$user=$this->user();
        $this->actingAs($user)->postJson('/api/account/following',['team_id'=>68864])->assertOk();
        $this->putJson('/api/account/primary-team',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.teams.0.remindersEnabled',false);
        $this->assertDatabaseCount('notification_preferences',0);
    }
    public function test_disabled_reminder_feature_never_enrolls_new_follows():void {
        config(['reminders.enabled'=>false]);$user=$this->user();
        $this->actingAs($user)->postJson('/api/account/following',['team_id'=>68864,'reminders_enabled'=>true])->assertOk()->assertJsonPath('following.remindersAvailable',false);
        $this->assertDatabaseCount('notification_preferences',0);
    }
    public function test_invalid_reminder_choice_is_rejected_before_adding_a_club():void {
        config(['reminders.enabled'=>true]);$user=$this->user();
        $this->actingAs($user)->postJson('/api/account/following',['team_id'=>68864,'reminders_enabled'=>'oui'])->assertUnprocessable();
        $this->putJson('/api/account/primary-team',['team_id'=>68864,'reminders_enabled'=>'oui'])->assertUnprocessable();
        $this->assertDatabaseCount('user_followed_teams',0);Http::assertNothingSent();
    }

}
