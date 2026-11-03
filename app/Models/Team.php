<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Team extends Model {
    protected $guarded=['id'];
    protected function casts(): array { return ['highlightly_id'=>'integer','api_football_id'=>'integer','last_seen_season'=>'integer']; }
}
