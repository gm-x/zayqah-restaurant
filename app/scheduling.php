<?php
function schedules_ready(): bool {
 static $ready;if($ready!==null)return $ready;try{q('SELECT menu_id FROM menu_schedules LIMIT 1');return $ready=true;}catch(PDOException $e){if(in_array((string)$e->getCode(),['42S02','HY000']))return $ready=false;throw $e;}
}
function live_sql(string $alias='m'): string {
 if(!preg_match('/^[a-z][a-z0-9_]*$/i',$alias))throw new InvalidArgumentException('Invalid menu alias.');
 $utc=db()->quote(now());if(!schedules_ready())return "($alias.status='published' AND $alias.expires_at>$utc)";
 $local=new DateTimeImmutable('now',new DateTimeZone(cfg('timezone')));$today=db()->quote($local->format('Y-m-d'));$yesterday=db()->quote($local->modify('-1 day')->format('Y-m-d'));$time=db()->quote($local->format('H:i'));$dayBit=1<<((int)$local->format('N')-1);$previousBit=1<<((int)$local->modify('-1 day')->format('N')-1);
 $todayMatch="(ms.weekday_mask & $dayBit)<>0 AND ms.start_date<=$today AND (ms.end_date IS NULL OR ms.end_date>=$today)";
 $previousMatch="(ms.weekday_mask & $previousBit)<>0 AND ms.start_date<=$yesterday AND (ms.end_date IS NULL OR ms.end_date>=$yesterday)";
 $weekly="ms.mode='weekly' AND ((ms.end_time>ms.start_time AND $todayMatch AND $time>=ms.start_time AND $time<ms.end_time) OR (ms.end_time<ms.start_time AND (($todayMatch AND $time>=ms.start_time) OR ($previousMatch AND $time<ms.end_time))))";
 return "($alias.status='published' AND ((NOT EXISTS(SELECT 1 FROM menu_schedules ms0 WHERE ms0.menu_id=$alias.id) AND $alias.expires_at>$utc) OR EXISTS(SELECT 1 FROM menu_schedules ms WHERE ms.menu_id=$alias.id AND ((ms.mode IN ('now','once') AND ms.start_at<=$utc AND ms.end_at>$utc AND $alias.expires_at>$utc) OR ($weekly)))))";
}
function schedule_for(int $id): ?array {
 static $cache;if(!schedules_ready())return null;if(!isset($cache)){$cache=[];foreach(all('SELECT * FROM menu_schedules') as $s)$cache[$s['menu_id']]=$s;}return $cache[$id]??null;
}
function window_for(array $item,?DateTimeImmutable $at=null): ?array {
 $schedule=schedule_for((int)$item['id']);$tz=new DateTimeZone(cfg('timezone'));$at=($at??new DateTimeImmutable('now',$tz))->setTimezone($tz);
 if(!$schedule){$end=new DateTimeImmutable($item['expires_at'],new DateTimeZone('UTC'));return $end>$at?['start'=>$at,'end'=>$end]:null;}
 if($schedule['mode']!=='weekly')return $schedule['end_at']?['start'=>new DateTimeImmutable($schedule['start_at'],new DateTimeZone('UTC')),'end'=>new DateTimeImmutable($schedule['end_at'],new DateTimeZone('UTC'))]:null;
 $begin=$at->setTime(0,0)->modify('-1 day');$startDate=new DateTimeImmutable($schedule['start_date'],$tz);if($startDate>$begin)$begin=$startDate;
 for($i=0;$i<9;$i++){$date=$begin->modify("+$i days");$ymd=$date->format('Y-m-d');if($ymd<$schedule['start_date']||($schedule['end_date']&&$ymd>$schedule['end_date']))continue;if(!((int)$schedule['weekday_mask']&(1<<((int)$date->format('N')-1))))continue;
  $start=new DateTimeImmutable($ymd.' '.$schedule['start_time'],$tz);$end=new DateTimeImmutable($ymd.' '.$schedule['end_time'],$tz);if($end<=$start)$end=$end->modify('+1 day');if($end>$at)return ['start'=>$start,'end'=>$end];
 }return null;
}
function live_until(array $item): string {$window=window_for($item);return $window?$window['end']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'):$item['expires_at'];}
function dish_is_live(int $id): bool {return (bool)one('SELECT id FROM menu_items m WHERE m.id=? AND '.live_sql('m'),[$id]);}
function menu_state(array $m): string {
 if($m['status']==='archived')return t('Archived');if($m['status']==='paused')return t('Paused');if($m['status']==='draft')return t('Draft');if(dish_is_live((int)$m['id']))return isset($m['category_active'])&&!$m['category_active']?t('Category hidden'):'Live';$w=window_for($m);return $w&&$w['start']>new DateTimeImmutable('now')?t('Scheduled'):t('Expired');
}
function utc_picker(string $value): ?string {$d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone(cfg('timezone')));return $d&&$d->format('Y-m-d\TH:i')===$value?$d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'):null;}
function parse_schedule(): array {
 $mode=val('schedule_mode')?:'now';if(!in_array($mode,['now','once','weekly','draft']))throw new InvalidArgumentException(t('Choose an availability option.'));
 $s=['mode'=>$mode,'start_at'=>null,'end_at'=>null,'weekday_mask'=>0,'start_date'=>null,'end_date'=>null,'start_time'=>null,'end_time'=>null];
 if(in_array($mode,['now','once'])){$s['start_at']=$mode==='now'?now():utc_picker(val('starts_at'));$s['end_at']=utc_picker(val('expires_at'));if(!$s['start_at']||!$s['end_at']||$s['end_at']<=$s['start_at']||$s['end_at']<=now())throw new InvalidArgumentException(t('Choose a valid start and end time; the end must be in the future.'));}
 if($mode==='weekly'){
  $days=is_array($_POST['weekdays']??null)?$_POST['weekdays']:[];foreach($days as $day)if(is_string($day)&&in_array($day,['1','2','3','4','5','6','7'],true))$s['weekday_mask']|=1<<((int)$day-1);
  if(!$s['weekday_mask'])throw new InvalidArgumentException(t('Choose at least one weekday.'));
  foreach(['start_date','end_date'] as $key){$v=val($key);if($key==='end_date'&&$v==='')continue;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v,new DateTimeZone(cfg('timezone')));if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException(t('Choose a valid schedule date.'));$s[$key]=$v;}
  if($s['end_date']&&$s['end_date']<$s['start_date'])throw new InvalidArgumentException(t('The final date must be on or after the first date.'));
  foreach(['start_time','end_time'] as $key){$v=val($key);if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$v))throw new InvalidArgumentException(t('Choose valid weekly start and end times.'));$s[$key]=$v;}
  if($s['start_time']===$s['end_time'])throw new InvalidArgumentException(t('Use different start and end times. An earlier end time means the next day.'));
 }return $s;
}
function save_schedule(int $id,array $s): void {
 q('DELETE FROM menu_schedules WHERE menu_id=?',[$id]);q('INSERT INTO menu_schedules (menu_id,mode,start_at,end_at,weekday_mask,start_date,end_date,start_time,end_time) VALUES (?,?,?,?,?,?,?,?,?)',[$id,...array_values($s)]);
}
function shuffled_feed(array $candidates,string $filterKey,bool $refresh=false): array {
 $_SESSION['food_feeds']??=[];if(count($_SESSION['food_feeds'])>8)array_shift($_SESSION['food_feeds']);$feed=$_SESSION['food_feeds'][$filterKey]??null;
 if($refresh||!$feed||$feed['expires']<time()||(!$feed['ids']&&$candidates)){$seed=bin2hex(random_bytes(16));$groups=[];foreach($candidates as $m)$groups[$m['seller_id']][]=(int)$m['id'];foreach($groups as &$ids)usort($ids,fn($a,$b)=>strcmp(hash('sha256',$seed.':dish:'.$a),hash('sha256',$seed.':dish:'.$b)));unset($ids);uksort($groups,fn($a,$b)=>strcmp(hash('sha256',$seed.':shop:'.$a),hash('sha256',$seed.':shop:'.$b)));$order=[];while($groups)foreach($groups as $seller=>&$ids){$order[]=array_shift($ids);if(!$ids)unset($groups[$seller]);}unset($ids);$feed=['ids'=>$order,'expires'=>time()+7200];$_SESSION['food_feeds'][$filterKey]=$feed;}
 $map=[];foreach($candidates as $m)$map[$m['id']]=$m;$result=[];foreach($feed['ids'] as $id)if(isset($map[$id]))$result[]=$map[$id];return $result;
}
