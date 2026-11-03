<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FootballMatch extends Model {
    protected $table='matches';protected $guarded=['id'];
    protected function casts():array { return ['fixture'=>'array','kickoff_at'=>'immutable_datetime','fetched_at'=>'immutable_datetime']; }
}
