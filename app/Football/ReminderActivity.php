<?php
namespace App\Football;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
class ReminderActivity {
    private const TASKS=['prepare'=>'Préparation des rappels','mail'=>'Traitement de la file d’e-mails'];
    private static function key(string $task):string { return 'reminders:activity:'.$task; }
    public static function start(string $task):void {
        $previous=Cache::get(self::key($task),[]);
        Cache::forever(self::key($task),['startedAt'=>now()->toIso8601String(),'finishedAt'=>null,'lastSuccessAt'=>$previous['lastSuccessAt']??null,'result'=>'running','prepared'=>null]);
    }
    public static function finish(string $task,string $result,?int $prepared=null):void {
        $record=Cache::get(self::key($task),[]);
        Cache::forever(self::key($task),[...$record,'finishedAt'=>now()->toIso8601String(),'lastSuccessAt'=>$result==='success'?now()->toIso8601String():($record['lastSuccessAt']??null),'result'=>$result,'prepared'=>$prepared]);
    }
    public static function snapshot():array {
        $tasks=[];
        foreach(self::TASKS as $task=>$label) {
            $record=Cache::get(self::key($task));
            // Le mode volontaire prime sur l'absence de passage ou un ancien incident.
            if(!Reminders::available())$state='disabled';
            elseif(ApiClient::mode()==='pause')$state='paused';
            elseif(!$record)$state='never';
            elseif($record['result']==='failed')$state='failed';
            elseif(CarbonImmutable::parse($record['finishedAt']??$record['startedAt'])->lt(now()->subMinutes(5)))$state='late';
            elseif($record['result']==='running')$state='running';
            elseif($record['result']==='skipped')$state='waiting';
            else $state='healthy';
            $tasks[]=['task'=>$task,'label'=>$label,'state'=>$state,'startedAt'=>$record['startedAt']??null,'finishedAt'=>$record['finishedAt']??null,'lastSuccessAt'=>$record['lastSuccessAt']??null,'result'=>$record['result']??null,'prepared'=>$record['prepared']??null];
        }
        return ['checkedAt'=>now()->toIso8601String(),'lateAfterMinutes'=>5,'tasks'=>$tasks];
    }
}
