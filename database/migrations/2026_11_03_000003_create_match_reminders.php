<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('notification_preferences',function(Blueprint $t) {
            $t->unsignedBigInteger('user_id');$t->unsignedBigInteger('team_id');$t->boolean('reminders_enabled')->default(false);$t->timestamp('enabled_at')->nullable();$t->timestamps();
            $t->primary(['user_id','team_id']);
            $t->foreign(['user_id','team_id'])->references(['user_id','team_id'])->on('user_followed_teams')->cascadeOnDelete();
        });
        Schema::create('matches',function(Blueprint $t) {
            $t->id();$t->string('provider',20);$t->unsignedBigInteger('external_id');$t->unsignedSmallInteger('season');
            $t->foreignId('home_team_id')->constrained('teams');$t->foreignId('away_team_id')->constrained('teams');
            $t->timestamp('kickoff_at')->index();$t->string('status',8);$t->json('fixture');$t->timestamp('fetched_at');$t->timestamps();
            $t->unique(['provider','external_id','season']);$t->unique(['season','home_team_id','away_team_id']);
        });
        Schema::create('notification_deliveries',function(Blueprint $t) {
            $t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $t->string('type',16)->default('reminder');$t->string('status',16)->default('pending')->index();$t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expected_kickoff_at');$t->timestamp('queued_at')->nullable();$t->timestamp('sent_at')->nullable();$t->string('last_error',40)->nullable();$t->timestamps();
            $t->unique(['user_id','match_id','type']);
        });
        Schema::create('jobs',function(Blueprint $t) {
            $t->bigIncrements('id');$t->string('queue')->index();$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs',function(Blueprint $t) {
            $t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();
        });
    }
    public function down():void {
        Schema::dropIfExists('failed_jobs');Schema::dropIfExists('jobs');Schema::dropIfExists('notification_deliveries');Schema::dropIfExists('matches');Schema::dropIfExists('notification_preferences');
    }
};
