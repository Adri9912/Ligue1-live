<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
class CheckV2 extends Command {
    protected $signature='app:check-v2';
    protected $description='Vérifier la préparation V2 sans migration, e-mail ou appel football';
    public function handle():int {
        $valid=true;$check=function(bool $ok,string $label)use(&$valid) { $this->line(($ok?'OK : ':'À préparer : ').$label);$valid=$valid&&$ok; };
        $check(in_array(config('database.default'),['mysql','mariadb'],true),'Connexion MySQL / MariaDB');
        if(app()->environment('production')) {
            $check(config('database.connections.'.config('database.default').'.database')==='ligue1-live_ligue1','Nom de base de production attendu');
            $check(!config('app.debug')&&str_starts_with(config('app.url'),'https://')&&config('session.secure'),'HTTPS, cookies Secure et débogage désactivé');
        }
        try {
            DB::connection()->getPdo();$check(true,'Connexion à la base');
            foreach(['users','sessions','teams','user_followed_teams','manual_refresh_counts','matches','notification_preferences','notification_deliveries','jobs','failed_jobs','password_reset_tokens'] as $table)$check(Schema::hasTable($table),'Table '.$table);
        } catch(Throwable $e) { $check(false,'Accès à la base (diagnostic privé non affiché)'); }
        $check(config('session.driver')==='database','Sessions en base');
        $check(config('mail.default')==='smtp'&&(bool)config('mail.mailers.smtp.host')&&(bool)config('mail.mailers.smtp.password')&&(bool)config('mail.from.address'),'Paramètres SMTP présents (livraison non testée ici)');
        $this->line('Comptes : '.(config('accounts.enabled')?'ouverts':'fermés').' ; rappels : '.(config('reminders.enabled')?'ouverts':'fermés').'.');
        $this->line('La tâche Alwaysdata et la réception des e-mails sont à vérifier séparément.');return $valid?0:1;
    }
}
