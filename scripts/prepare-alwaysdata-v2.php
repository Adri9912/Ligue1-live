<?php
// À placer dans un dossier privé hors du site ; contrôle et sauvegarde seulement.
$home=getenv('HOME');$root=$home.'/www/ligue1-live';$private=__DIR__;
if(PHP_SAPI!=='cli'||!is_file($root.'/vendor/autoload.php')||!is_file($private.'/config.json'))exit(1);
require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $data=json_decode(file_get_contents($private.'/config.json'),true,512,JSON_THROW_ON_ERROR);$db=$data['db'];
    if(($db['DB_DATABASE']??'')!=='ligue1-live_ligue1'||($db['DB_HOST']??'')!=='mysql-ligue1-live.alwaysdata.net')throw new RuntimeException;
    config(['database.connections.v2_preparation'=>['driver'=>'mysql','host'=>$db['DB_HOST'],'port'=>(int)($db['DB_PORT']??3306),'database'=>$db['DB_DATABASE'],'username'=>$db['DB_USERNAME'],'password'=>$db['DB_PASSWORD'],'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true]]);
    $connection=Illuminate\Support\Facades\DB::connection('v2_preparation');$version=$connection->selectOne('SELECT VERSION() AS version');
    $tables=$connection->select('SHOW TABLES');echo 'Base attendue accessible ; serveur '.$version->version.' ; '.count($tables)." table(s) présente(s). Aucune migration.\n";
    if(in_array('--backup',$argv,true)) {
        config(['database.default'=>'v2_preparation']);require_once $private.'/BackupDatabase.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->registerCommand(new App\Console\Commands\BackupDatabase);
        $result=Illuminate\Support\Facades\Artisan::call('app:backup-db',['--directory'=>$private.'/backups']);echo Illuminate\Support\Facades\Artisan::output();if($result!==0)exit(1);
    }
} catch(Throwable $e) { echo "Accès MySQL ou sauvegarde à corriger ; aucun diagnostic privé affiché.\n";exit(1); }
try {
    config(['mail.mailers.smtp'=>$data['smtp'],'mail.from'=>$data['from']]);
    $transport=Illuminate\Support\Facades\Mail::mailer('smtp')->getSymfonyTransport();$transport->start();$transport->stop();
    echo "Connexion SMTP authentifiée depuis Alwaysdata ; aucun e-mail envoyé par ce contrôle.\n";
} catch(Throwable $e) { echo "Authentification SMTP à corriger ; aucun diagnostic privé affiché.\n";exit(1); }
echo "Configuration active du site, tables et tâches planifiées inchangées.\n";
