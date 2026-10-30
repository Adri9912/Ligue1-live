<?php
namespace Tests\Feature;
use Tests\TestCase;
class FrontendRoutesTest extends TestCase
{
    public function test_spa_routes_assets_and_private_files_are_served_safely(): void
    {
        $directory=base_path('dist');$made=!is_file($directory.'/index.html');
        if(!is_dir($directory.'/assets'))mkdir($directory.'/assets',0775,true);
        $file='test-'.bin2hex(random_bytes(6)).'.js';file_put_contents($directory.'/assets/'.$file,'export const value=1;');
        if($made)file_put_contents($directory.'/index.html','<!doctype html><div id="app"></div>');
        try {
            $page=$this->get('/mon-equipe')->assertOk()->assertHeader('Cache-Control','no-cache, public')->assertHeader('X-Content-Type-Options','nosniff');
            $this->assertSame($directory.'/index.html',$page->baseResponse->getFile()->getPathname());
            $response=$this->get('/assets/'.$file)->assertOk();
            $this->assertStringContainsString('text/javascript',$response->headers->get('Content-Type'));
            $this->assertStringContainsString('immutable',$response->headers->get('Cache-Control'));
            foreach(['/.env','/composer.json','/storage/logs/laravel.log','/assets/../../.env','/api/unknown'] as $path) {
                $response=$this->get($path);
                $this->assertContains($response->status(),[403,404],$path.' doit rester inaccessible');
            }
        } finally { unlink($directory.'/assets/'.$file);if($made)unlink($directory.'/index.html'); }
    }
}
