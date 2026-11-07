<?php
namespace App\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class News
{
    // Adresse publique fournie par Maxifoot ; aucune clé ni donnée utilisateur transmise.
    public const FEED = 'http://rss.maxifoot.com/football-general.xml';

    public static function get(): array
    {
        $cached = Cache::get('news:maxifoot:data');
        if ($cached && $cached['fetchedAt'] > now()->timestamp - 600) return [...$cached, 'articles'=>self::recent($cached['articles']), 'stale'=>false];
        $lock = Cache::lock('news:maxifoot:lock', 15);
        if (Cache::has('news:maxifoot:backoff') || !$lock->get()) return self::unavailable($cached);
        try {
            $response = Http::timeout(8)->connectTimeout(4)->withoutRedirecting()->get(self::FEED);
            if (!$response->successful()) throw new \RuntimeException('RSS indisponible');
            $data = ['articles'=>self::parse($response->body()), 'fetchedAt'=>now()->timestamp, 'source'=>'Maxifoot'];
            Cache::put('news:maxifoot:data', $data, 86400);
            return [...$data, 'stale'=>false];
        } catch (\Throwable) {
            Cache::put('news:maxifoot:backoff', true, 60);
            return self::unavailable($cached);
        } finally { $lock->release(); }
    }

    private static function unavailable(?array $cached): array
    {
        return [...($cached ?? ['articles'=>[], 'fetchedAt'=>null, 'source'=>'Maxifoot']), 'articles'=>self::recent($cached['articles'] ?? []), 'stale'=>true, 'error'=>'Le fil d’actualités est momentanément indisponible.'];
    }

    private static function recent(array $articles): array
    {
        $now = now()->timestamp;
        return array_values(array_filter($articles, fn($article)=>($published=strtotime($article['publishedAt'])) >= $now-86400 && $published <= $now));
    }

    public static function parse(string $body): array
    {
        if (strlen($body) > 1000000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $body)) throw new \RuntimeException('RSS invalide');
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false || !isset($xml->channel)) throw new \RuntimeException('RSS invalide');
            $articles = [];
            foreach ($xml->channel->item as $item) {
                $url = trim((string)$item->link);
                $parts = parse_url($url);
                if (!$parts || !in_array($parts['scheme'] ?? '', ['http','https'], true) || !in_array($parts['host'] ?? '', ['www.maxifoot.fr','news.maxifoot.fr','maxifoot.fr'], true) || isset($parts['user']) || isset($parts['port'])) continue;
                // ponytail: sélection des rubriques françaises connues, à compléter si Maxifoot en renomme une.
                $section = explode('/', trim($parts['path'] ?? '', '/'))[0];
                if (!in_array($section, ['ligue-1','psg','paris-sg','om','marseille','lyon','lille','monaco','lens','rennes','nice','strasbourg','toulouse','brest','nantes','auxerre','angers','lorient','le-havre','paris-fc','metz','troyes','montpellier','st-etienne','saint-etienne','reims'], true)) continue;
                $title = trim(html_entity_decode(strip_tags((string)$item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $published = strtotime((string)$item->pubDate);
                if (!$title || !$published || mb_strlen($title) > 400) continue;
                $url = preg_replace('/^http:/', 'https:', $url);
                $articles[$url] = ['id'=>hash('sha256',$url), 'title'=>$title, 'url'=>$url, 'publishedAt'=>gmdate('c',$published), 'source'=>'Maxifoot'];
            }
            usort($articles, fn($a,$b)=>strcmp($b['publishedAt'],$a['publishedAt']));
            return self::recent($articles);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
