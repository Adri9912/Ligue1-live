<?php
namespace Tests\Feature;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
class ConcurrencyTest extends TestCase
{
    public function test_parallel_php_requests_share_the_cache_and_quota_lock(): void
    {
        $directory=sys_get_temp_dir().'/l1-parallel-'.bin2hex(random_bytes(8));mkdir($directory,0700,true);
        $script=<<<'PHP'
<?php
require ROOT.'/vendor/autoload.php';
$app=require ROOT.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default'=>'file','cache.stores.file.path'=>DIR.'/data','cache.stores.file.lock_path'=>DIR.'/locks','football.highlightly_key'=>'fake-test','football.daily_limit'=>1,'football.spacing_ms'=>0]);
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(function() {
    file_put_contents(DIR.'/calls','request'.PHP_EOL,FILE_APPEND|LOCK_EX);
    usleep(150000);
    return Illuminate\Support\Facades\Http::response(['data'=>['shared']],200);
});
try {
    $client=new App\Football\ApiClient('highlightly');
    $result=$client->request($argv[1],[],600000);
    echo json_encode(['data'=>$result['data'],'used'=>$client->budget()['used']]);
} catch(App\Football\ApiError $e) { echo json_encode(['status'=>$e->getStatusCode()]); }
PHP;
        $script=str_replace(['ROOT','DIR'],[var_export(base_path(),true),var_export($directory,true)],$script);
        file_put_contents($directory.'/worker.php',$script);
        try {
            $run=function(string $first,string $second) use($directory) {
                $a=new Process([PHP_BINARY,$directory.'/worker.php',$first]);$b=new Process([PHP_BINARY,$directory.'/worker.php',$second]);
                $a->start();$b->start();$a->wait();$b->wait();
                $this->assertTrue($a->isSuccessful(),$a->getErrorOutput());$this->assertTrue($b->isSuccessful(),$b->getErrorOutput());
                return [json_decode($a->getOutput(),true,512,JSON_THROW_ON_ERROR),json_decode($b->getOutput(),true,512,JSON_THROW_ON_ERROR)];
            };
            [$a,$b]=$run('same','same');
            $this->assertSame(['shared'],$a['data']['data']);$this->assertSame($a,$b);
            $this->assertCount(1,file($directory.'/calls'));
            File::deleteDirectory($directory.'/data');File::deleteDirectory($directory.'/locks');unlink($directory.'/calls');
            $results=$run('first','second');$this->assertCount(1,array_filter($results,fn($r)=>($r['status']??null)===429));$this->assertCount(1,file($directory.'/calls'));
        } finally { File::deleteDirectory($directory); }
    }
}
