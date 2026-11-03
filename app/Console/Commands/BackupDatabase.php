<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;
class BackupDatabase extends Command {
    protected $signature='app:backup-db {--directory= : Dossier privé de destination}';
    protected $description='Sauvegarder MySQL sans mot de passe dans la commande ou les logs';
    public function handle():int {
        $directory=$this->option('directory')?:storage_path('app/private/backups');
        if(!str_starts_with($directory,'/')||(!is_dir($directory)&&!mkdir($directory,0700,true))) { $this->error('Choisissez un dossier privé absolu.');return 1; }
        $directory=realpath($directory);
        foreach([public_path(),base_path('dist'),base_path('.git')] as $public)if($directory===$public||str_starts_with($directory,$public.'/')) { $this->error('Le dossier doit être hors des fichiers publics et de Git.');return 1; }
        if((fileperms($directory)&0077)!==0) { $this->error('Le dossier de sauvegarde doit être privé (droits 700).');return 1; }
        $config=DB::connection()->getConfig();
        if(!in_array($config['driver'],['mysql','mariadb'],true)||!preg_match('/^[A-Za-z0-9_-]+$/',$config['database']??'')) { $this->error('Une base MySQL ou MariaDB explicitement configurée est requise.');return 1; }
        $client=$partial=$output=null;
        try {
            $quote=fn($value)=>'"'.str_replace(["\\","\"","\n","\r"],["\\\\","\\\"","\\n","\\r"],(string)$value).'"';
            $client=tempnam($directory,'client-');chmod($client,0600);
            $settings="[client]\n";foreach(['host','port','username'=>'user','password','unix_socket'=>'socket'] as $key=>$name) {
                $key=is_int($key)?$name:$key;if(($config[$key]??'')!=='')$settings.=$name.'='.$quote($config[$key])."\n";
            }
            file_put_contents($client,$settings);
            $version=new Process(['mysqldump','--version']);$version->mustRun();
            $args=['mysqldump','--defaults-extra-file='.$client,'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--hex-blob','--default-character-set=utf8mb4'];
            if(!str_contains($version->getOutput(),'MariaDB'))$args=[...$args,'--set-gtid-purged=OFF','--column-statistics=0'];
            $partial=tempnam($directory,'partial-');chmod($partial,0600);$output=gzopen($partial,'wb6');if(!$output)throw new \RuntimeException;
            $dump=new Process([...$args,$config['database']]);$dump->setTimeout(120);
            $dump->run(function($type,$chunk)use($output) { if($type===Process::OUT&&gzwrite($output,$chunk)!==strlen($chunk))throw new \RuntimeException; });
            gzclose($output);$output=null;if(!$dump->isSuccessful())throw new \RuntimeException;
            $path=$directory.'/db-'.now()->utc()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql.gz';
            if(!rename($partial,$path))throw new \RuntimeException;$partial=null;
            $this->info('Sauvegarde privée créée : '.$path);return 0;
        } catch(Throwable $e) {
            $this->error('Sauvegarde impossible. Vérifiez le client mysqldump, les accès MySQL et l’espace disque. Aucun diagnostic privé n’est affiché.');return 1;
        } finally {
            if(is_resource($output))gzclose($output);foreach([$client,$partial] as $file)if($file&&is_file($file))unlink($file);
        }
    }
}
