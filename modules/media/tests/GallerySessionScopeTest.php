<?php
declare(strict_types=1);
require_once __DIR__.'/../services/GallerySessionScope.php';
use Media\Services\GallerySessionScope;
function check(bool $ok): void { if (!$ok) throw new RuntimeException('scope assertion failed'); }
check(GallerySessionScope::resolve([]) === null);
check(GallerySessionScope::resolve(['doctor_id'=>'1']) === null);
check(GallerySessionScope::resolve(['user_id'=>'1']) === null);
check(GallerySessionScope::resolve(['user_id'=>'1','doctor_id'=>'1'])['doctor_id'] === '1');
check(GallerySessionScope::resolve(['auth_user_id'=>'1','active_entity_type'=>'doctor','active_entity_id'=>'1'])['doctor_id'] === '1');
foreach ([['active_doctor_id'=>'2'], ['entity_type'=>'clinic'], ['entity_id'=>'2'], ['actor_role'=>'assistant'], ['operator_id'=>'7'], ['subscriptions_dev_session_fixture'=>'1']] as $extra) {
    check(GallerySessionScope::resolve(array_merge(['user_id'=>'1','doctor_id'=>'1'], $extra)) === null);
}
check(GallerySessionScope::resolve(['user_id'=>'1','doctor_id'=>'1','subscriptions_dev_session_fixture'=>'1'], true)['doctor_id'] === '1');
$html=file_get_contents(__DIR__.'/../../../index.html');
check((bool)preg_match('~src="assets/js/fotos\.js\?v=[A-Za-z0-9-]+"~', $html));
check(!str_contains(file_get_contents(__DIR__.'/../../../assets/js/fotos.js'), 'localStorage'));
echo "PASS: session scope, entity fallback, conflicting scope, role, dev gate and versioned server-only loader\n";
