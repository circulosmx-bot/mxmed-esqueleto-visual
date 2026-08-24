<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class SessionCookieDescriptor
{
    public function __construct(private string $name, private string $value, private bool $secure = true, private bool $httpOnly = true, private string $sameSite = 'Lax', private string $path = '/', private ?string $domain = null, private ?int $maxAge = null)
    {
        if ($this->name !== '__Host-mxmed_session' || !$this->secure || !$this->httpOnly || $this->sameSite !== 'Lax' || $this->path !== '/' || $this->domain !== null) throw new \InvalidArgumentException('invalid_session_cookie_descriptor');
    }

    public static function forToken(SessionToken $token, ?int $maxAge = null): self { return new self('__Host-mxmed_session', $token->value(), true, true, 'Lax', '/', null, $maxAge); }
    public static function deletion(): self { return new self('__Host-mxmed_session', '', true, true, 'Lax', '/', null, -1); }
    public function name(): string { return $this->name; }
    public function value(): string { return $this->value; }
    public function secure(): bool { return $this->secure; }
    public function httpOnly(): bool { return $this->httpOnly; }
    public function sameSite(): string { return $this->sameSite; }
    public function path(): string { return $this->path; }
    public function domain(): ?string { return $this->domain; }
    public function maxAge(): ?int { return $this->maxAge; }
    public function toArray(): array { return ['name'=>$this->name,'value'=>$this->value,'secure'=>$this->secure,'httponly'=>$this->httpOnly,'samesite'=>$this->sameSite,'path'=>$this->path,'domain'=>$this->domain,'max_age'=>$this->maxAge]; }
}
