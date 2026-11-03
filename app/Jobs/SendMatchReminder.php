<?php
namespace App\Jobs;
use App\Football\ApiClient;
use App\Football\Reminders;
use App\Mail\ReminderMailer;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;
class SendMatchReminder implements ShouldQueue {
    use Queueable;
    public int $tries=10;public int $timeout=25;
    public function __construct(public int $deliveryId) {}
    public function handle(ReminderMailer $mailer):void {
        $delivery=NotificationDelivery::find($this->deliveryId);
        if(!$delivery||$delivery->status!=='pending')return;
        if(!Reminders::available()){ $delivery->update(['status'=>'cancelled','last_error'=>'disabled']);return; }
        if(ApiClient::mode()==='pause'){ $this->release(60);return; }
        try {
            if(!Reminders::canSend($delivery)){ $delivery->update(['status'=>'cancelled','last_error'=>'no_longer_eligible']);return; }
            $mailer->connect();
        } catch(Throwable $e) {
            $delivery->increment('attempts');$delivery->update(['last_error'=>'connection_or_data','status'=>$delivery->attempts>=3?'failed':'pending']);
            if($delivery->attempts<3)$this->release(60);
            return;
        }
        // Relecture après la connexion : désabonnement ou changement d'adresse entre-temps.
        $delivery->refresh();
        if(!Reminders::canSend($delivery)){ $delivery->update(['status'=>'cancelled','last_error'=>'no_longer_eligible']);return; }
        $claimed=DB::transaction(function()use($delivery) {
            $row=NotificationDelivery::whereKey($delivery->id)->lockForUpdate()->first();
            if(!$row||$row->status!=='pending')return false;
            $row->update(['status'=>'sending','attempts'=>$row->attempts+1,'last_error'=>null]);return true;
        });
        if(!$claimed)return;
        try {
            $mailer->send($delivery);
            $delivery->update(['status'=>'sent','sent_at'=>now(),'last_error'=>null]);
        } catch(Throwable $e) {
            // SMTP a pu accepter le message : ne pas provoquer un doublon par un renvoi aveugle.
            $delivery->update(['status'=>'uncertain','last_error'=>'delivery_uncertain']);
        }
    }
    public function failed(?Throwable $error):void {
        NotificationDelivery::whereKey($this->deliveryId)->where('status','pending')->update(['status'=>'failed','last_error'=>'worker_failed']);
    }
}
