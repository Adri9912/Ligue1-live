<?php
namespace App\Mail;
use App\Models\NotificationDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
class ReminderMail extends Mailable {
    public function __construct(public NotificationDelivery $delivery) {}
    public function build():static {
        $fixture=$this->delivery->fixture->fixture;
        $kickoff=$this->delivery->fixture->kickoff_at->setTimezone('Europe/Paris')->locale('fr');
        return $this->subject('Votre match approche : '.$fixture['home']['name'].' – '.$fixture['away']['name'])->view('mail.reminder')->text('mail.reminder-text')->with([
            'fixture'=>$fixture,'kickoff'=>$kickoff->translatedFormat('l j F Y').' à '.$kickoff->format('H').' h '.$kickoff->format('i'),
            'matchUrl'=>url('/matchs/'.$fixture['id']),
            'unsubscribeUrl'=>URL::temporarySignedRoute('reminders.unsubscribe',now()->addDays(30),['user'=>$this->delivery->user_id]),
        ]);
    }
}
