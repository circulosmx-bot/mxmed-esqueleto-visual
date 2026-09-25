<?php
declare(strict_types=1);

/** Read-only LON07B projection. Times and bounds are UTC. */
final class ClinicalMeasurementTrends
{
    private const PAGE_DEFAULT = 50;
    private const PAGE_MAX = 100;
    private const CODES = ['blood_pressure','heart_rate','respiratory_rate','temperature','oxygen_saturation','weight','height','waist'];
    // Read-only accepted physical units; no conversion and no expansion of the write contract.
    private const UNITS = ['blood_pressure'=>['mmHg'],'heart_rate'=>['bpm'],'respiratory_rate'=>['rpm'],
        'temperature'=>['°C'],'oxygen_saturation'=>['%'],'weight'=>['kg','lb'],'height'=>['cm'],'waist'=>['cm']];

    public function __construct(private PDO $pdo) {}

    public function assertReady(): void
    {
        $stmt=$this->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME IN ('effective_at_authority','invalidated_at','invalidated_by_user_id','invalidation_reason')");
        if ((int)$stmt->fetchColumn()!==4) throw new RuntimeException('SCHEMA_NOT_READY');
    }

    public static function options(array $query): array
    {
        $view=(string)($query['view'] ?? 'series');
        if (!in_array($view,['series','latest','history','points','prior'],true)) throw new InvalidArgumentException('INVALID_VIEW');
        $excludeEncounter=(string)($query['exclude_encounter_id'] ?? '');
        if ($view==='prior' && (!preg_match('/^[1-9][0-9]*$/D',$excludeEncounter) || strlen($excludeEncounter)>18)) throw new InvalidArgumentException('CURRENT_ENCOUNTER_REQUIRED');
        $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        $from=self::time((string)($query['from'] ?? $now->modify('-12 months')->format('Y-m-d H:i:s')));
        $to=self::time((string)($query['to'] ?? $now->format('Y-m-d H:i:s')));
        if ($from>$to || $from<$to->modify('-5 years')) throw new InvalidArgumentException('INVALID_TIME_RANGE');
        $rawSize=(string)($query['limit'] ?? self::PAGE_DEFAULT);
        if (!preg_match('/^[1-9][0-9]*$/D',$rawSize) || (int)$rawSize>self::PAGE_MAX) throw new InvalidArgumentException('INVALID_PAGE_SIZE');
        $code=(string)($query['code'] ?? '');$unit=(string)($query['unit'] ?? '');$source=(string)($query['source'] ?? '');
        if ($code!=='' && !in_array($code,[...self::CODES,'pain'],true)) throw new InvalidArgumentException('INVALID_CODE');
        if ($source!=='' && !in_array($source,['direct_measurement','patient_report','import'],true)) throw new InvalidArgumentException('INVALID_SOURCE');
        if (strlen($unit)>32 || preg_match('/[\x00-\x1F]/',$unit)) throw new InvalidArgumentException('INVALID_UNIT');
        $component=(string)($query['component'] ?? '');
        if (!in_array($component,['','systolic','diastolic'],true) || ($component!=='' && $code!=='blood_pressure')) throw new InvalidArgumentException('INVALID_COMPONENT');
        if ($view==='points' && ($code==='' || $unit==='' || $source==='' || $code==='pain' || ($code==='blood_pressure' && $component===''))) throw new InvalidArgumentException('SERIES_FILTER_REQUIRED');
        if ($view==='points' && !in_array($unit,self::UNITS[$code]??[],true)) throw new InvalidArgumentException('INVALID_SERIES_UNIT');
        $cursor=null;
        if (isset($query['cursor'])) {
            $decoded=base64_decode((string)$query['cursor'],true);
            $parts=$decoded===false?null:json_decode($decoded,true);
            if (!is_array($parts) || count($parts)!==2 || !is_string($parts[0]) || !self::validTime($parts[0]) || !is_int($parts[1]) || $parts[1]<1) throw new InvalidArgumentException('INVALID_CURSOR');
            $cursor=$parts;
        }
        return compact('view','from','to','code','unit','source','component','cursor')+['limit'=>(int)$rawSize,'exclude_encounter_id'=>(int)$excludeEncounter];
    }

    private static function validTime(string $value): bool
    {
        $time=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        return $time!==false && $time->format('Y-m-d H:i:s')===$value;
    }

    private static function time(string $value): DateTimeImmutable
    {
        if (!self::validTime($value)) throw new InvalidArgumentException('INVALID_TIME_RANGE');
        return new DateTimeImmutable($value,new DateTimeZone('UTC'));
    }

    private static function key(string $code,string $unit,string $source,string $component=''): string
    {
        return implode('|',$component===''?[$code,$unit,$source]:[$code,$unit,$source,$component]);
    }

    private static function classify(array $row): string
    {
        if (!empty($row['invalidated_at'])) return 'INVALIDATED';
        $code=(string)$row['code'];
        if ($code==='pain' || !in_array($code,self::CODES,true)) return 'NUMERIC_NON_COMPARABLE';
        if (!in_array((string)$row['unit'],self::UNITS[$code],true)) return 'INCOMPATIBLE_UNIT';
        if (!in_array((string)$row['source'],['direct_measurement','patient_report','import'],true)) return 'INVALID_SOURCE';
        if (!self::validTime((string)$row['effective_at'])) return 'INVALID_EFFECTIVE_TIME';
        if ((string)$row['effective_at_authority']!=='EXPLICIT_EFFECTIVE_TIME') return 'UNCONFIRMED_EFFECTIVE_TIME';
        if ($code==='blood_pressure') return (is_numeric($row['systolic_mm_hg']) && is_numeric($row['diastolic_mm_hg']) && (float)$row['systolic_mm_hg']>0 && (float)$row['diastolic_mm_hg']>0) ? 'TREND_ELIGIBLE' : 'INVALID_VALUE';
        return is_numeric($row['value_numeric']) ? 'TREND_ELIGIBLE' : 'INVALID_VALUE';
    }

    private static function item(array $row): array
    {
        $reason=self::classify($row);$code=(string)$row['code'];
        return ['observation_id'=>(int)$row['observation_id'],'encounter_key'=>'enc:'.$row['encounter_id'],
            'code'=>$code,'unit'=>(string)$row['unit'],'source'=>(string)$row['source'],
            'value_numeric'=>$code==='blood_pressure'?null:$row['value_numeric'],
            'systolic_mm_hg'=>$code==='blood_pressure'?$row['systolic_mm_hg']:null,
            'diastolic_mm_hg'=>$code==='blood_pressure'?$row['diastolic_mm_hg']:null,
            'effective_at'=>(string)$row['effective_at'],'effective_at_authority'=>(string)$row['effective_at_authority'],
            'invalidated_at'=>$row['invalidated_at']??null,'invalidated_by_user_id'=>$row['invalidated_by_user_id']??null,
            'invalidation_reason'=>$row['invalidation_reason']??null,
            'recorded_at'=>(string)$row['recorded_at'],'trend_eligible'=>$reason==='TREND_ELIGIBLE',
            'classification'=>$reason==='TREND_ELIGIBLE'?'TREND_ELIGIBLE':'HISTORY_ONLY',
            'ineligibility_reason'=>$reason==='TREND_ELIGIBLE'?null:$reason,
            'encounter_has_amendment'=>(bool)$row['has_amendment']];
    }

    private static function components(array $item): array
    {
        if ($item['code']!=='blood_pressure') return [['series_key'=>self::key($item['code'],$item['unit'],$item['source']),'component'=>null,'value_numeric'=>$item['value_numeric']]];
        return [
            ['series_key'=>self::key($item['code'],$item['unit'],$item['source'],'systolic'),'component'=>'systolic','value_numeric'=>$item['systolic_mm_hg']],
            ['series_key'=>self::key($item['code'],$item['unit'],$item['source'],'diastolic'),'component'=>'diastolic','value_numeric'=>$item['diastolic_mm_hg']],
        ];
    }

    public function read(string $doctor,string $patient,array $options): array
    {
        $from=$options['from']->format('Y-m-d H:i:s');$to=$options['to']->format('Y-m-d H:i:s');
        $view=$options['view'];
        if ($view==='prior') return $this->prior($doctor,$patient,$options['exclude_encounter_id']);
        if ($view==='series' || $view==='latest') return $this->series($doctor,$patient,$from,$to);
        $where="e.doctor_id=:doctor AND e.patient_id=:patient AND o.effective_at>=:from_time AND o.effective_at<=:to_time";
        $params=[':doctor'=>$doctor,':patient'=>$patient,':from_time'=>$from,':to_time'=>$to];
        foreach (['code','unit','source'] as $name) if ($options[$name]!=='') {$where.=" AND o.$name=:$name";$params[":$name"]=$options[$name];}
        if ($view==='points') {
            $where.=" AND o.invalidated_at IS NULL AND o.effective_at_authority='EXPLICIT_EFFECTIVE_TIME'";
            $where.=" AND o.code IN ('blood_pressure','heart_rate','respiratory_rate','temperature','oxygen_saturation','weight','height','waist')";
            $where.=" AND ((o.code='blood_pressure' AND o.systolic_mm_hg>0 AND o.diastolic_mm_hg>0) OR (o.code<>'blood_pressure' AND o.value_numeric IS NOT NULL))";
        }
        if ($options['cursor']!==null) {$where.=' AND (o.effective_at<:cursor_time OR (o.effective_at=:cursor_time_equal AND o.observation_id<:cursor_id))';$params[':cursor_time']=$options['cursor'][0];$params[':cursor_time_equal']=$options['cursor'][0];$params[':cursor_id']=$options['cursor'][1];}
        $sql="SELECT o.observation_id,o.encounter_id,o.code,o.value_numeric,o.unit,o.systolic_mm_hg,o.diastolic_mm_hg,o.effective_at,o.effective_at_authority,o.recorded_at,o.source,o.invalidated_at,o.invalidated_by_user_id,o.invalidation_reason,EXISTS(SELECT 1 FROM clinical_encounter_amendments a WHERE a.encounter_id=e.encounter_id) AS has_amendment FROM clinical_observations o JOIN clinical_encounters e ON e.encounter_id=o.encounter_id WHERE $where ORDER BY o.effective_at DESC,o.observation_id DESC LIMIT :limit";
        $stmt=$this->pdo->prepare($sql);
        foreach($params as $name=>$value)$stmt->bindValue($name,$value,$name===':cursor_id'?PDO::PARAM_INT:PDO::PARAM_STR);
        $stmt->bindValue(':limit',$options['limit']+1,PDO::PARAM_INT);$stmt->execute();
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$hasMore=count($rows)>$options['limit'];$rows=array_slice($rows,0,$options['limit']);
        $items=[];
        foreach($rows as $row){$item=self::item($row);if($view==='points') {if(!$item['trend_eligible'])continue;foreach(self::components($item) as $component){if($options['component']!=='' && $component['component']!==$options['component'])continue;$items[]=array_merge($item,$component);}}else{$items[]=$item;}}
        $last=end($rows);$cursor=$hasMore&&$last?base64_encode(json_encode([(string)$last['effective_at'],(int)$last['observation_id']])):null;
        return ['items'=>$items,'next_cursor'=>$cursor,'has_more'=>$hasMore,'limit'=>$options['limit'],'from'=>$from,'to'=>$to,'ordering'=>'effective_at DESC, observation_id DESC'];
    }

    /** VIS29: latest reusable prior per concept, using the canonical write units and time authority.
     * No lower date bound: an older height must not disappear behind recent vital signs.
     * Exclusion precedes ranking; current observations cannot hide a prior candidate.
     */
    private function prior(string $doctor,string $patient,int $excludeEncounter): array
    {
        require_once __DIR__.'/clinical_observations.php';
        $eligible=[];
        $params=[':doctor'=>$doctor,':patient'=>$patient,':exclude'=>$excludeEncounter];
        foreach (clinical_observation_catalog() as $code=>$definition) {
            $eligible[]="(o.code=:code_$code AND o.unit=:unit_$code)";
            $params[":code_$code"]=$code;$params[":unit_$code"]=$definition['unit'];
        }
        $units=implode(' OR ',$eligible);
        $sql="SELECT * FROM (SELECT o.*,EXISTS(SELECT 1 FROM clinical_encounter_amendments a WHERE a.encounter_id=e.encounter_id) AS has_amendment,
            ROW_NUMBER() OVER (PARTITION BY o.code ORDER BY o.effective_at DESC,o.observation_id DESC) AS prior_rank
            FROM clinical_observations o JOIN clinical_encounters e ON e.encounter_id=o.encounter_id
            WHERE e.doctor_id=:doctor AND e.patient_id=:patient AND o.encounter_id<>:exclude AND o.invalidated_at IS NULL
            AND o.effective_at_authority='EXPLICIT_EFFECTIVE_TIME' AND o.effective_at<=UTC_TIMESTAMP()
            AND o.source IN ('direct_measurement','patient_report','import') AND ($units)
            AND ((o.code='blood_pressure' AND o.systolic_mm_hg>0 AND o.diastolic_mm_hg>0)
                OR (o.code<>'blood_pressure' AND o.value_numeric IS NOT NULL))) ranked
            WHERE prior_rank=1 ORDER BY effective_at DESC,observation_id DESC";
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);
        return ['items'=>array_map(static fn(array $row):array=>self::item($row),$stmt->fetchAll(PDO::FETCH_ASSOC)),
            'latest_semantics'=>'LATEST_EXPLICIT_PRIOR_PER_CONCEPT','excluded_encounter_id'=>$excludeEncounter];
    }

    private function series(string $doctor,string $patient,string $from,string $to): array
    {
        // Partition by the physical series key; never select from a global first-N page.
        $sql="SELECT * FROM (SELECT o.observation_id,o.encounter_id,o.code,o.value_numeric,o.unit,o.systolic_mm_hg,o.diastolic_mm_hg,o.effective_at,o.effective_at_authority,o.recorded_at,o.source,o.invalidated_at,o.invalidated_by_user_id,o.invalidation_reason,EXISTS(SELECT 1 FROM clinical_encounter_amendments a WHERE a.encounter_id=e.encounter_id) AS has_amendment,ROW_NUMBER() OVER (PARTITION BY o.code,o.unit,o.source ORDER BY o.effective_at DESC,o.observation_id DESC) AS series_rank FROM clinical_observations o JOIN clinical_encounters e ON e.encounter_id=o.encounter_id WHERE e.doctor_id=:doctor AND e.patient_id=:patient AND o.effective_at>=:from_time AND o.effective_at<=:to_time AND o.invalidated_at IS NULL AND o.effective_at_authority='EXPLICIT_EFFECTIVE_TIME' AND ((o.code='blood_pressure' AND o.unit='mmHg' AND o.systolic_mm_hg>0 AND o.diastolic_mm_hg>0) OR (o.code='heart_rate' AND o.unit='bpm' AND o.value_numeric IS NOT NULL) OR (o.code='respiratory_rate' AND o.unit='rpm' AND o.value_numeric IS NOT NULL) OR (o.code='temperature' AND o.unit='°C' AND o.value_numeric IS NOT NULL) OR (o.code='oxygen_saturation' AND o.unit='%' AND o.value_numeric IS NOT NULL) OR (o.code='weight' AND o.unit IN ('kg','lb') AND o.value_numeric IS NOT NULL) OR (o.code='height' AND o.unit='cm' AND o.value_numeric IS NOT NULL) OR (o.code='waist' AND o.unit='cm' AND o.value_numeric IS NOT NULL))) ranked WHERE series_rank=1 ORDER BY code,unit,source";
        $stmt=$this->pdo->prepare($sql);$stmt->execute([':doctor'=>$doctor,':patient'=>$patient,':from_time'=>$from,':to_time'=>$to]);
        $series=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$item=self::item($row);foreach(self::components($item) as $component)$series[]=['series_key'=>$component['series_key'],'code'=>$item['code'],'unit'=>$item['unit'],'source'=>$item['source'],'component'=>$component['component'],'latest_comparable_observation'=>array_merge($item,$component)];}
        return ['series'=>$series,'from'=>$from,'to'=>$to,'ordering'=>'effective_at DESC, observation_id DESC','latest_semantics'=>'LATEST_COMPARABLE_OBSERVATION'];
    }
}
