<?php
namespace Tests\Feature;
use App\Football\ApiClient;
use App\Football\Portraits;
use App\Football\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class PortraitsTest extends TestCase {
    protected function setUp():void { parent::setUp();Cache::flush();Http::preventStrayRequests();config(['football.refresh_mode'=>'auto']); }
    public function test_verified_identity_shared_cache_and_provider_name_birth_match():void {
        $url='https://r2.thesportsdb.com/images/media/player/cutout/example.png';
        Portraits::register(1,'Ousmane Dembélé','1997-05-15');
        Http::fake(['*'=>Http::response(['player'=>[['strPlayer'=>'Ousmane Dembele','dateBorn'=>'1997-05-15','strCutout'=>$url]]])]);
        $this->assertNull(Portraits::cached(1));
        $this->getJson('/api/portraits/1?p=Other')->assertOk()->assertJsonPath('photo',$url)->assertJsonPath('source','TheSportsDB');
        $this->assertSame($url,Portraits::cached(1));
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',$url);Http::assertSentCount(1);
        Http::assertSent(fn($r)=>$r['p']==='Ousmane Dembélé'&&!$r->hasHeader('Authorization'));
        config(['football.refresh_mode'=>'pause']);$this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',$url);Http::assertSentCount(1);
    }
    public function test_unknown_players_and_missing_birth_do_not_search_arbitrary_names():void {
        $this->assertNull(Portraits::register(1,'Unknown',null));$this->getJson('/api/portraits/1?name=Someone&birth=2000-01-01')->assertNotFound();
        Portraits::register(2,'Known','2000-01-01');config(['football.refresh_mode'=>'pause']);$this->getJson('/api/portraits/2')->assertOk()->assertJsonPath('photo',null);
        config(['football.refresh_mode'=>'manual']);$this->getJson('/api/portraits/2')->assertOk()->assertJsonPath('photo',null);Http::assertNothingSent();
    }
    public function test_wrong_identity_duplicate_results_and_untrusted_image_urls_are_not_used():void {
        $currentRows=[];
        Http::fake(function()use(&$currentRows){return Http::response(['player'=>$currentRows]);});
        foreach([
            [['strPlayer'=>'Someone Else','dateBorn'=>'2000-01-01','strCutout'=>'https://r2.thesportsdb.com/images/media/player/cutout/a.png']],
            [['strPlayer'=>'Known','dateBorn'=>'1999-01-01','strCutout'=>'https://r2.thesportsdb.com/images/media/player/cutout/a.png']],
            array_fill(0,2,['strPlayer'=>'Known','dateBorn'=>'2000-01-01','strCutout'=>'https://r2.thesportsdb.com/images/media/player/cutout/a.png']),
            [['strPlayer'=>'Known','dateBorn'=>'2000-01-01','strCutout'=>'https://r2.thesportsdb.com.evil.test/images/media/player/cutout/a.png']],
            [['strPlayer'=>'Known','dateBorn'=>'2000-01-01','strCutout'=>'javascript:alert(1)']],
        ] as $rows) {
            Cache::flush();Portraits::register(1,'Known','2000-01-01');$currentRows=$rows;
            $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',null);
            $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',null);
        }
        Http::assertSentCount(5);
    }
    public function test_request_limits_errors_and_negative_results_are_shared():void {
        Portraits::register(1,'Known','2000-01-01');
        Cache::put('portraits:minute',array_fill(0,25,Schedule::now()),61);
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',null)->assertJsonStructure(['retryAt']);Http::assertNothingSent();
        Cache::forget('portraits:minute');Http::fakeSequence()->pushStatus(429)->push(['player'=>null]);
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonStructure(['retryAt']);
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonStructure(['retryAt']);Http::assertSentCount(1);
        $this->travel(61)->seconds();$this->getJson('/api/portraits/1')->assertOk()->assertJsonMissingPath('retryAt')->assertJsonPath('photo',null);
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',null);Http::assertSentCount(2);$this->travelBack();
    }
    public function test_changed_identity_does_not_reuse_old_photo_and_malformed_provider_is_retryable():void {
        Portraits::register(1,'Known','2000-01-01');Http::fakeSequence()->push(['player'=>[['strPlayer'=>'Known','dateBorn'=>'2000-01-01','strThumb'=>'https://www.thesportsdb.com/images/media/player/thumb/a.jpg']]])->push(['error'=>'unavailable']);
        $this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo','https://www.thesportsdb.com/images/media/player/thumb/a.jpg');
        Portraits::register(1,'Known','1999-01-01');$this->getJson('/api/portraits/1')->assertOk()->assertJsonPath('photo',null)->assertJsonStructure(['retryAt']);Http::assertSentCount(2);
    }
}
