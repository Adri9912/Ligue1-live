<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('teams',function(Blueprint $t) {
            $t->id();$t->string('identity_key')->unique();$t->string('name');$t->string('full_name');$t->string('code',12);$t->text('logo')->nullable();
            $t->unsignedBigInteger('highlightly_id')->nullable()->unique();$t->unsignedBigInteger('api_football_id')->nullable()->unique();$t->unsignedSmallInteger('last_seen_season');$t->timestamps();
        });
        Schema::table('users',fn(Blueprint $t)=>$t->foreignId('primary_team_id')->nullable()->constrained('teams')->nullOnDelete());
        Schema::create('user_followed_teams',function(Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('team_id')->constrained()->cascadeOnDelete();$t->primary(['user_id','team_id']);
        });
        Schema::create('manual_refresh_counts',function(Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->date('day');$t->unsignedTinyInteger('used')->default(0);$t->primary(['user_id','day']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('manual_refresh_counts');Schema::dropIfExists('user_followed_teams');
        Schema::table('users',fn(Blueprint $t)=>$t->dropConstrainedForeignId('primary_team_id'));Schema::dropIfExists('teams');
    }
};
