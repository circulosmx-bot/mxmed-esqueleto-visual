<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\IdentityAccount;
final class OperatorInvitationRecipients
{
    private function __construct(private bool $synthetic){}
    public static function synthetic():self{return new self(true);}
    public static function canonical():self{return new self(false);}
    public function normalize(mixed $input):string
    {
        if(!is_string($input))throw new \InvalidArgumentException('operator_contact_invalid');
        $email=IdentityAccount::normalizeEmail($input);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||($this->synthetic&&!str_ends_with($email,'@example.invalid')))
            throw new \InvalidArgumentException('operator_contact_invalid');
        return $email; // Existence is deliberately not disclosed; acceptance binds verified Identity.
    }
}
