<?php
namespace App\Models;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
#[Fillable(['email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    protected $attributes = ['role'=>'user', 'active'=>true];
    public function followedTeams() { return $this->belongsToMany(Team::class, 'user_followed_teams'); }
    protected function casts(): array {
        return ['email_verified_at'=>'datetime','password'=>'hashed','active'=>'boolean','primary_team_id'=>'integer'];
    }
}
