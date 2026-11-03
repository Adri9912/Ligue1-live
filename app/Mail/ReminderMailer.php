<?php
namespace App\Mail;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;
class ReminderMailer {
    public function connect():void {
        $transport=Mail::mailer()->getSymfonyTransport();
        if(method_exists($transport,'start'))$transport->start();
    }
    public function send(NotificationDelivery $delivery):void {
        Mail::to($delivery->user->email)->send(new ReminderMail($delivery));
    }
}
