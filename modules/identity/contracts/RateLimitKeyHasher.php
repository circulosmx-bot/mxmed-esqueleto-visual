<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class RateLimitKeyHasher
{
    public function __construct(private string $pepper)
    {
        if ($pepper === '') throw new \InvalidArgumentException('rate_limit_pepper_required');
    }

    public function hash(string $dimensionValue): string
    {
        return hash_hmac('sha256', $dimensionValue, $this->pepper);
    }
}
