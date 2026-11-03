<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
class DatabaseBackupTest extends TestCase {
    private string $folder;private string $path;
    protected function setUp():void {
        parent::setUp();$this->folder=sys_get_temp_dir().'/l1-backup-'.bin2hex(random_bytes(8));mkdir($this->folder,0700);mkdir($this->folder.'/bin',0700);mkdir($this->folder.'/private',0700);
        $this->path=getenv('PATH');$new=$this->folder.'/bin:'.$this->path;putenv('PATH='.$new);$_SERVER['PATH']=$new;$_ENV['PATH']=$new;
        config(['database.default'=>'mysql','database.connections.mysql.password'=>'dummy-private-password']);
    }
    protected function tearDown():void {
        putenv('PATH='.$this->path);$_SERVER['PATH']=$this->path;$_ENV['PATH']=$this->path;File::deleteDirectory($this->folder);parent::tearDown();
    }
    private function dumpClient(bool $fail=false):void {
        $script="#!/bin/sh\nif [ \"\$1\" = '--version' ]; then echo 'mysqldump Ver 8.4'; exit 0; fi\n";
        if($fail)$script.="echo 'dummy-private-password' >&2\nexit 1\n";
        else $script.="case \"\$*\" in *dummy-private-password*) exit 1;; esac\nconfig=\${1#--defaults-extra-file=}\n[ \"\$(stat -c %a \"\$config\")\" = 600 ] || exit 1\nprintf 'CREATE TABLE backup_check (id INT);\\n'\n";
        file_put_contents($this->folder.'/bin/mysqldump',$script);chmod($this->folder.'/bin/mysqldump',0700);
    }
    public function test_backup_is_private_compressed_and_temporary_credentials_are_removed():void {
        $this->dumpClient();$this->artisan('app:backup-db',['--directory'=>$this->folder.'/private'])->assertExitCode(0);
        $files=glob($this->folder.'/private/*');$this->assertCount(1,$files);$this->assertStringEndsWith('.sql.gz',$files[0]);$this->assertSame(0600,fileperms($files[0])&0777);
        $this->assertStringContainsString('CREATE TABLE backup_check',gzdecode(file_get_contents($files[0])));
    }
    public function test_failed_dump_does_not_leave_credentials_partial_sql_or_private_diagnostics():void {
        $this->dumpClient(true);$this->artisan('app:backup-db',['--directory'=>$this->folder.'/private'])->doesntExpectOutputToContain('dummy-private-password')->assertExitCode(1);
        $this->assertSame([],glob($this->folder.'/private/*'));
    }
    public function test_public_and_relative_directories_are_rejected():void {
        $this->artisan('app:backup-db',['--directory'=>public_path()])->assertExitCode(1);
        $this->artisan('app:backup-db',['--directory'=>'relative-directory'])->assertExitCode(1);
    }
}
