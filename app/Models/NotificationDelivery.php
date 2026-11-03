<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NotificationDelivery extends Model {
    protected $guarded=['id'];
    protected $attributes=['status'=>'pending','type'=>'reminder','attempts'=>0];
    public function user() { return $this->belongsTo(User::class); }
    public function fixture() { return $this->belongsTo(FootballMatch::class,'match_id'); }
    protected function casts():array { return ['expected_kickoff_at'=>'immutable_datetime','queued_at'=>'immutable_datetime','sent_at'=>'immutable_datetime','attempts'=>'integer']; }
}
