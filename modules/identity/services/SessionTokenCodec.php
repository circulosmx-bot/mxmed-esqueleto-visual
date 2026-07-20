<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\SessionToken;
use Identity\Contracts\SessionTokenDigest;

final class SessionTokenCodec
{
    public function __construct(private string $pepper)
    {
        if ($this->pepper === '' || strlen($this->pepper) < 16) throw new \InvalidArgumentException('session_pepper_required');
    }
    public function issue(): SessionToken { return SessionToken::generate(); }
    public function digest(SessionToken|string $token): SessionTokenDigest
    {
        $value = $token instanceof SessionToken ? $token->value() : $token;
        if ($value === '') throw new \InvalidArgumentException('session_token_required');
        return new SessionTokenDigest(hash_hmac('sha256', $value, $this->pepper));
    }
}
