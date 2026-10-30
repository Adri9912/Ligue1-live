<?php
namespace App\Football;
use Carbon\CarbonImmutable;
class Schedule
{
    public const HOUR = 3600000;
    public const TEN_MINUTES = 600000;
    public static function now(): int { return (int) now()->getTimestampMs(); }
    public static function timestamp(string $date): int { return (int) CarbonImmutable::parse($date)->getTimestampMs(); }
    public static function parisDay(int $time): string { return CarbonImmutable::createFromTimestampMs($time)->setTimezone('Europe/Paris')->format('Y-m-d'); }
    public static function sameDay(int $a, int $b): bool { return self::parisDay($a) === self::parisDay($b); }
    public static function nextDay(int $time): int { return (int) CarbonImmutable::createFromTimestampMs($time)->setTimezone('Europe/Paris')->startOfDay()->addDay()->getTimestampMs(); }
    public static function active(array $matches, int $now): bool
    {
        foreach ($matches as $m) {
            $start = self::timestamp($m['date']);
            if ($now >= $start && $now < $start + 4*self::HOUR && in_array($m['status'], ['1H','2H','HT','ET','BT','P','LIVE','INT','NS','TBD'], true)) return true;
        }
        return false;
    }
    public static function fixturesFresh(array $entry, int $now, bool $manual = false): bool
    {
        return $manual || self::active($entry['data'], $now) ? $now-$entry['time'] < self::TEN_MINUTES : self::sameDay($entry['time'], $now);
    }
    public static function nextFixtures(?array $entry, int $now): int
    {
        if (!$entry) return $now;
        $next = self::nextDay($entry['time']);
        if (self::active($entry['data'], $now)) $next = min($next, $entry['time']+self::TEN_MINUTES);
        foreach ($entry['data'] as $m) {
            $start = self::timestamp($m['date']);
            if (in_array($m['status'], ['NS','TBD'], true) && $start > $now) $next = min($next, $start);
        }
        return max($now, $next);
    }
    public static function signature(array $matches): string
    {
        $parts = [];
        foreach ($matches as $m) if (in_array($m['status'], ['FT','AET','PEN','AWD','WO'], true)) $parts[] = $m['id'].':'.($m['homeGoals']??'null').':'.($m['awayGoals']??'null');
        sort($parts, SORT_STRING); return implode('|', $parts);
    }
    public static function standingsFresh(array $entry, string $signature, int $now): bool
    {
        return $now-$entry['time'] < self::HOUR || self::sameDay($entry['time'], $now) && ($entry['signature']??null) === $signature;
    }
}
