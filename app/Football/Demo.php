<?php
namespace App\Football;
use Carbon\CarbonImmutable;
class Demo
{
    private array $data;
    public function __construct()
    {
        $this->data=json_decode(file_get_contents(resource_path('football/demo.json')),true,512,JSON_THROW_ON_ERROR);
        $days=(int) CarbonImmutable::parse($this->data['generatedDate'],'Europe/Paris')->diffInDays(now('Europe/Paris')->startOfDay());
        foreach ($this->data['matches'] as &$m) $m['date']=CarbonImmutable::parse($m['date'])->addDays($days)->toIso8601String();unset($m);
        foreach ($this->data['details'] as &$d) $d['fixture']['date']=CarbonImmutable::parse($d['fixture']['date'])->addDays($days)->toIso8601String();
    }
    public function matches(): array { return ['matches'=>$this->data['matches'],'fetchedAt'=>Schedule::now(),'stale'=>false]; }
    public function overview(): array { return ['teams'=>$this->data['teams'],'standings'=>$this->data['standings'],'fetchedAt'=>Schedule::now(),'stale'=>false]; }
    public function detail(int $id): array {
        $d=collect($this->data['details'])->firstWhere('fixture.id',$id);
        if (!$d) throw new ApiError('Match introuvable.',404);
        return [...$d,'fetchedAt'=>Schedule::now(),'stale'=>false];
    }
    public function players(string $kind): array { return ['players'=>$this->data[$kind],'fetchedAt'=>Schedule::now(),'stale'=>false]; }
}
