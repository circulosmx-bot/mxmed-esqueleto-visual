<?php
declare(strict_types=1);

/** Invoked only by the existing disposable composition runner, after its schema guard. */
function identityUiCsrfCompositionChecks(\Identity\Http\IdentityHttpComposition $normal): void
{
    $preview=\Identity\Http\IdentityHttpComposition::fromProcessEnvironment();
    $localToken=$normal->csrf()->issuePreAuth();
    $previewToken=$preview->csrf()->issuePreAuth();
    check('X06',!$normal->csrf()->validPreAuth($previewToken));
    check('X07',!$preview->csrf()->validPreAuth($localToken));
    $clock=new class implements \Identity\Contracts\Clock {
        public int $offset=0;
        public function now(): DateTimeImmutable { return (new DateTimeImmutable('@1700000000'))->modify('+'.$this->offset.' seconds'); }
    };
    $csrf=new \Identity\Http\CsrfTokenService(bin2hex(random_bytes(32)),900,$clock,'https://localhost:8443');
    $token=$csrf->issuePreAuth(); $clock->offset=900;
    check('csrf_900s_boundary',$csrf->validPreAuth($token)); $clock->offset=901;
    check('X05',!$csrf->validPreAuth($token));
}
