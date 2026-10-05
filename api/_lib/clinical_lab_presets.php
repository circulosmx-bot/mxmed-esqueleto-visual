<?php
declare(strict_types=1);

/** Future definitions remain inactive until LAB-CAT05C explicitly activates them. */
function clinical_lab_preset_authority(?array $processOverride = null): array
{
    static $config;
    if ($processOverride !== null) $config = $processOverride;
    return $config ??= json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/lab_order_presets_v1.json'), true, 512, JSON_THROW_ON_ERROR);
}

function clinical_lab_preset_definition(mixed $request, bool $allowFuture = false): array
{
    $authority = clinical_lab_preset_authority();
    if (($authority['contract'] ?? null) !== 'lab_order_presets' || ($authority['version'] ?? null) !== 1) throw new RuntimeException('LAB_PRESET_AUTHORITY_INVALID');
    if (!is_array($request) || array_is_list($request) || array_diff(array_keys($request), ['preset_key','preset_version'])) throw new InvalidArgumentException('LAB_PRESET_REQUEST_INVALID');
    $key = $request['preset_key'] ?? null;
    if (!is_string($key) || !isset($authority['presets'][$key])) throw new InvalidArgumentException('LAB_PRESET_UNKNOWN');
    $rule = $authority['presets'][$key];
    if (($request['preset_version'] ?? null) !== ($rule['preset_version'] ?? null) || !is_int($rule['preset_version'] ?? null)) throw new InvalidArgumentException('LAB_PRESET_VERSION_INVALID');
    if (!$allowFuture && ($rule['status'] ?? null) !== 'ACTIVE') throw new InvalidArgumentException('LAB_PRESET_INACTIVE');
    $keys = $rule['component_study_keys'] ?? null;
    if (!is_array($keys) || !array_is_list($keys) || !$keys || count($keys) > 100 || count($keys) !== count(array_unique($keys)) || !is_string($rule['display_name'] ?? null) || trim($rule['display_name']) === '') throw new RuntimeException('LAB_PRESET_COMPONENTS_INVALID');
    foreach ($keys as $component) if (!is_string($component) || !preg_match('/^[a-z][a-z0-9_]*$/D', $component)) throw new RuntimeException('LAB_PRESET_COMPONENT_INVALID');
    return $rule + ['preset_key'=>$key];
}

/** Returns ordinary canonical items plus unique applications; no pseudo-item is created. */
function clinical_lab_preset_expand(PDO $pdo, array $inputs, mixed $applications, string $group, bool $allowFuture = false): array
{
    if ($applications === null || $applications === []) return ['items'=>$inputs,'applications'=>[],'preview'=>[]];
    if (!is_array($applications) || !array_is_list($applications) || count($applications) > 20) throw new InvalidArgumentException('LAB_PRESET_APPLICATIONS_INVALID');
    $routing = json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/study_order_routing_v1.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = $pdo->prepare('SELECT study_type_id,study_type_key,display_name_es,category_key,is_active FROM clinical_study_types WHERE study_type_key=? OR study_type_id=? LIMIT 1');
    $resolve = static function(?string $key, ?int $id) use ($catalog): array {
        $catalog->execute([$key ?? '', $id ?? -1]);
        $row = $catalog->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (int)$row['is_active'] !== 1 || ($key !== null && $row['study_type_key'] !== $key) || ($id !== null && (int)$row['study_type_id'] !== $id)) throw new InvalidArgumentException('LAB_PRESET_COMPONENT_INACTIVE_OR_MISSING');
        return $row;
    };
    $seen = [];
    foreach ($inputs as $input) {
        if (!is_array($input)) throw new InvalidArgumentException('ORDER_ITEMS_INVALID');
        if (isset($input['study_type_key']) || isset($input['study_type_id'])) {
            $row = $resolve(isset($input['study_type_key']) ? (string)$input['study_type_key'] : null, isset($input['study_type_id']) ? (int)$input['study_type_id'] : null);
            $seen[(int)$row['study_type_id']] = true;
        }
    }
    $unique = [];$preview = [];
    foreach ($applications as $application) {
        $rule = clinical_lab_preset_definition($application, $allowFuture);
        $key = $rule['preset_key'];
        if (isset($unique[$key])) continue; // Reapplying the same preset is idempotent.
        $new = [];$existing = [];$components = [];$specimens = [];
        foreach ($rule['component_study_keys'] as $componentKey) {
            $row = $resolve($componentKey, null);
            if ($row['category_key'] !== 'LABORATORIO' || ($routing['studies'][$componentKey] ?? null) !== $group) throw new InvalidArgumentException('LAB_PRESET_CROSS_ROUTE_INVALID');
            $id = (int)$row['study_type_id'];$components[] = ['study_type_id'=>$id,'study_type_key'=>$componentKey,'display_name'=>$row['display_name_es']];
            if (isset($seen[$id])) $existing[] = $componentKey;
            else { $inputs[] = ['study_type_id'=>$id,'study_type_key'=>$componentKey];$seen[$id] = true;$new[] = $componentKey; }
            $specimenRule = clinical_specimen_authority()['studies'][$componentKey] ?? null;
            $specimens[$componentKey] = $specimenRule['fixed_specimen_type_key'] ?? ($specimenRule['specimen_mode'] ?? 'NOT_STRUCTURED');
        }
        $unique[$key] = ['preset_key'=>$key,'preset_version'=>$rule['preset_version']];
        $preview[] = ['preset_key'=>$key,'display_name'=>$rule['display_name'],'components'=>$components,
            'new_keys'=>$new,'already_selected_keys'=>$existing,'specimen_requirements'=>$specimens];
    }
    if (count($inputs) > 100) throw new InvalidArgumentException('BATCH_STUDY_LIMIT');
    return ['items'=>$inputs,'applications'=>array_values($unique),'preview'=>$preview];
}

/** Maps the issued ordinary study items to each applied preset without creating a preset item. */
function clinical_lab_preset_snapshot(PDO $pdo, mixed $applications, array $items, bool $allowFuture = false): array
{
    if (!is_array($applications) || !array_is_list($applications) || count($applications) > 20) throw new InvalidArgumentException('LAB_PRESET_APPLICATIONS_INVALID');
    $routing = json_decode((string)file_get_contents(__DIR__.'/../../modules/clinical/catalog/study_order_routing_v1.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = $pdo->prepare('SELECT study_type_id,category_key,is_active FROM clinical_study_types WHERE study_type_key=?');
    $byKey = [];
    foreach ($items as $item) {
        if (!is_string($item['study_type_key'] ?? null)) continue;
        if (isset($byKey[$item['study_type_key']])) throw new InvalidArgumentException('LAB_PRESET_DUPLICATE_COMPONENT_ITEM');
        $byKey[$item['study_type_key']] = $item;
    }
    $seen = [];$snapshots = [];
    foreach ($applications as $application) {
        $rule = clinical_lab_preset_definition($application, $allowFuture);
        if (isset($seen[$rule['preset_key']])) continue;
        $seen[$rule['preset_key']] = true;
        $components = [];
        foreach ($rule['component_study_keys'] as $key) {
            $item = $byKey[$key] ?? null;
            if (!is_array($item) || !is_int($item['study_type_id'] ?? null) || !is_string($item['order_item_id'] ?? null)) throw new InvalidArgumentException('LAB_PRESET_COMPONENT_NOT_ISSUED');
            $catalog->execute([$key]);
            $row = $catalog->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['is_active'] !== 1 || $row['category_key'] !== 'LABORATORIO'
                || (int)$row['study_type_id'] !== $item['study_type_id'] || ($routing['studies'][$key] ?? null) !== 'CLINICAL_LAB') {
                throw new InvalidArgumentException('LAB_PRESET_COMPONENT_AUTHORITY_MISMATCH');
            }
            $components[] = ['study_type_id'=>$item['study_type_id'],'study_type_key'=>$key,
                'display_name'=>$item['study_display_name'],'order_item_id'=>$item['order_item_id']];
        }
        $snapshots[] = ['authority_version'=>1,'preset_key'=>$rule['preset_key'],'preset_version'=>$rule['preset_version'],
            'display_name'=>$rule['display_name'],'components'=>$components];
    }
    return $snapshots;
}
