<?php
namespace Tests\Feature;

use App\Football\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Cache::flush(); Http::preventStrayRequests(); $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-07T16:00:00+01:00')); }
    private function item(string $title, string $url, string $date = 'Sat, 07 Nov 2026 12:00:00 +0100'): string
    {
        return '<item><title><![CDATA['.$title.']]></title><link>'.htmlspecialchars($url, ENT_XML1).'</link><pubDate>'.$date.'</pubDate></item>';
    }
    private function rss(string $items): string { return '<?xml version="1.0"?><rss version="2.0"><channel>'.$items.'</channel></rss>'; }

    public function test_safe_french_news_sorted_deduplicated_and_shared_between_visitors(): void
    {
        $a='https://www.maxifoot.fr/ligue-1/a-foot-1.htm'; $b='https://www.maxifoot.fr/lille/b-foot-2.htm';
        $body=$this->rss($this->item('L1 : une rencontre', $a).$this->item('Mercato Lille : une signature', $b, 'Sat, 07 Nov 2026 14:00:00 +0100').$this->item('L1 : une rencontre', $a).$this->item('Tottenham : une annonce','https://www.maxifoot.fr/tottenham/a.htm').$this->item('Piège','javascript:alert(1)').$this->item('Piège','https://www.maxifoot.fr.evil.test/ligue-1/a.htm'));
        Http::fake([News::FEED=>Http::response($body,200,['Content-Type'=>'application/rss+xml'])]);
        $this->getJson('/api/news')->assertOk()->assertJsonCount(2,'articles')->assertJsonPath('articles.0.url',$b)->assertJsonPath('source','Maxifoot')->assertJsonPath('stale',false);
        $this->getJson('/api/news')->assertOk(); Http::assertSentCount(1);
        Http::assertSent(fn($request)=>!$request->hasHeader('Authorization')&&!$request->hasHeader('x-rapidapi-key'));
    }
    public function test_failure_uses_last_good_news_and_retries_are_limited(): void
    {
        Http::fakeSequence()->push($this->rss($this->item('L1 : une rencontre','https://www.maxifoot.fr/ligue-1/a.htm')))->pushStatus(500);
        $this->getJson('/api/news')->assertOk()->assertJsonCount(1,'articles');
        $this->travel(601)->seconds();
        $this->getJson('/api/news')->assertOk()->assertJsonPath('stale',true)->assertJsonCount(1,'articles');
        $this->getJson('/api/news')->assertOk()->assertJsonPath('stale',true); Http::assertSentCount(2);
    }
    public function test_malformed_or_entity_feeds_fail_without_exposing_or_inventing_content(): void
    {
        Http::fake([News::FEED=>Http::response('<!DOCTYPE rss [<!ENTITY secret SYSTEM "file:///etc/passwd">]><rss><channel>&secret;</channel></rss>')]);
        $this->getJson('/api/news')->assertStatus(503)->assertJsonCount(0,'articles')->assertJsonPath('stale',true);
        $this->getJson('/api/news')->assertStatus(503); Http::assertSentCount(1);
        $this->assertStringNotContainsString('root:',json_encode(News::get()));
        $this->expectException(\RuntimeException::class); News::parse('<html>unavailable</html>');
    }

    public function test_only_last_24_hours_remain_even_when_the_feed_is_cached_or_unavailable(): void
    {
        $url='https://www.maxifoot.fr/ligue-1/';
        $body=$this->rss($this->item('L1 : récente',$url.'recent.htm').$this->item('L1 : limite',$url.'limit.htm','Fri, 06 Nov 2026 16:00:00 +0100').$this->item('L1 : ancienne',$url.'old.htm','Fri, 06 Nov 2026 15:59:59 +0100').$this->item('L1 : future',$url.'future.htm','Sat, 07 Nov 2026 17:00:00 +0100'));
        Http::fakeSequence()->push($body)->pushStatus(500);
        $this->getJson('/api/news')->assertOk()->assertJsonCount(2,'articles');
        $this->travel(1)->seconds();
        $this->getJson('/api/news')->assertOk()->assertJsonCount(1,'articles'); Http::assertSentCount(1);
        $this->travel(601)->seconds();
        $this->getJson('/api/news')->assertOk()->assertJsonPath('stale',true)->assertJsonCount(1,'articles');
    }
}
