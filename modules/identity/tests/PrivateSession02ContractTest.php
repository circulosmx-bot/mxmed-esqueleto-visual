<?php
declare(strict_types=1);

function session02Check(string $name, bool $condition): void
{
    if (!$condition) throw new RuntimeException('FAIL:'.$name);
    echo $name."=PASS\n";
}

$root=dirname(__DIR__,3);
$read=static fn(string $path): string => (string)file_get_contents($root.'/'.$path);
$entry=$read('index.html');
$private=$read('private-app.php');
$session=$read('assets/js/core/private-session.js');
$login=$read('public/identity/identity-access.js');
$review=$read('api/local-review/index.php');
$profiles=$read('api/profiles/index.php');
$agenda=$read('api/agenda/index.php');

session02Check('AUTH01_neutral_entry',!str_contains($entry,'mx-global-header')&&!str_contains($entry,'data-doctor-id'));
session02Check('AUTH02_server_private_gate',str_contains($private,'PrivateApplicationAccessResolver')&&str_contains($private,"Location: /public/identity/acceso.php"));
session02Check('AUTH06_07_global_401',str_contains($session,'response.status === 401')&&str_contains($session,"lock('expired'"));
session02Check('AUTH08_403_distinct',str_contains($session,'response.status === 403')&&str_contains($session,'mxmed:authorization-denied'));
session02Check('AUTH09_logout',str_contains($session,"method: 'POST'")&&str_contains($session,"result.state !== 'LOGOUT_SUCCESS'"));
session02Check('AUTH10_bfcache',str_contains($session,"addEventListener('pagehide'")&&str_contains($session,"addEventListener('pageshow'"));
session02Check('AUTH11_multitab',str_contains($session,"BroadcastChannel(CHANNEL_NAME)")&&str_contains($session,"addEventListener('storage'"));
session02Check('AUTH12_storage',str_contains($session,'mxmed_rx_draft_v1:')&&str_contains($session,'mxmedActivePatientId'));
session02Check('AUTH13_14_review_authority',str_contains($review,'PrivateApplicationAccessResolver')&&str_contains($review,'validAuthenticated')&&str_contains($review,'issueAuthenticated'));
session02Check('AUTH15_public_profile_preserved',str_contains($profiles,"\$segments[0] === 'private'")&&str_contains($profiles,'canonical_identity'));
session02Check('AUTH16_public_agenda_preserved',str_contains($agenda,'is_public_agenda_route')&&str_contains($agenda,'canonical_identity'));
session02Check('AUTH19_no_mutation_replay',!str_contains($session,'location.reload()')||str_contains($session,"retry.onclick"));
session02Check('AUTH20_pending_abort',str_contains($session,'abortPending()')&&str_contains($session,'requestGeneration !== generation'));
session02Check('safe_login_destination',str_contains($login,'parsed.origin !== window.location.origin')&&str_contains($login,"parsed.pathname === '/private-app.php'"));
echo "PrivateSession02ContractTest=PASS\n";
