<?php
declare(strict_types=1);
namespace Identity\PhysicianAdministration;
/** Canonical read contract. Composition supplies its own source; no environment defaults. */
interface Source
{
    public function search(array $ids,string $query,string $after,int $limit):array;
    public function summary(string $id):?array;
    public function operators(string $id):array;
}
