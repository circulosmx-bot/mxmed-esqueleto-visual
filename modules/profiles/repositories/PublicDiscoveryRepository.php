<?php
declare(strict_types=1);

namespace Profiles\Repositories;

use PDO;

final class PublicDiscoveryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array{state?:string,city?:string,specialty?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function search(array $filters, int $page, int $pageSize): array
    {
        $where = [
            "p.profile_status = 'active'",
            'p.is_public_candidate = 1',
            "TRIM(p.display_name) <> ''",
            "TRIM(p.professional_license) <> ''",
            "TRIM(p.specialty_primary) <> ''",
        ];
        $params = [];
        $locationWhere = ['c.doctor_id = p.doctor_id'];

        if (($filters['state'] ?? '') !== '') {
            $locationWhere[] = 'LOWER(TRIM(c.estado)) = LOWER(TRIM(:state))';
            $params['state'] = $filters['state'];
        }
        if (($filters['city'] ?? '') !== '') {
            $locationWhere[] = 'LOWER(TRIM(c.municipio)) = LOWER(TRIM(:city))';
            $params['city'] = $filters['city'];
        }
        if (($filters['specialty'] ?? '') !== '') {
            $where[] = 'LOWER(TRIM(p.specialty_primary)) = LOWER(TRIM(:specialty))';
            $params['specialty'] = $filters['specialty'];
        }

        $where[] = 'EXISTS (SELECT 1 FROM consultorios c WHERE ' . implode(' AND ', $locationWhere) . ')';
        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM profiles_doctors p WHERE ' . $whereSql);
        foreach ($params as $name => $value) {
            $count->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int)$count->fetchColumn();

        $planColumn = $this->firstExistingColumn(
            'profiles_doctors',
            ['plan_code', 'profile_plan', 'plan_name', 'subscription_plan', 'commercial_plan']
        );
        $planProjection = $planColumn === null
            ? 'NULL AS plan_code'
            : 'p.' . $this->quoteIdentifier($planColumn) . ' AS plan_code';

        $sql = 'SELECT
                    p.doctor_id,
                    p.display_name,
                    p.prefix,
                    p.professional_license,
                    p.specialty_primary,
                    p.photo_url,
                    p.avatar_url,
                    p.logo_url,
                    ' . $planProjection . '
                FROM profiles_doctors p
                WHERE ' . $whereSql . '
                ORDER BY LOWER(TRIM(p.display_name)) ASC, p.doctor_id ASC
                LIMIT :page_size OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $pageSize, PDO::PARAM_INT);
        $statement->execute();
        $profileRows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach (is_array($profileRows) ? $profileRows : [] as $profileRow) {
            $location = $this->firstMatchingLocation((string)$profileRow['doctor_id'], $filters);
            if ($location === null) {
                continue;
            }
            $items[] = $profileRow + ['location' => $location];
        }

        return ['items' => $items, 'total' => $total];
    }

    private function firstMatchingLocation(string $doctorId, array $filters): ?array
    {
        $where = ['doctor_id = :doctor_id'];
        $params = ['doctor_id' => $doctorId];
        if (($filters['state'] ?? '') !== '') {
            $where[] = 'LOWER(TRIM(estado)) = LOWER(TRIM(:location_state))';
            $params['location_state'] = $filters['state'];
        }
        if (($filters['city'] ?? '') !== '') {
            $where[] = 'LOWER(TRIM(municipio)) = LOWER(TRIM(:location_city))';
            $params['location_city'] = $filters['city'];
        }

        $statement = $this->pdo->prepare(
            'SELECT consultorio_id, titulo, calle, num_ext, num_int, colonia, cp, municipio, estado
               FROM consultorios
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY consultorio_id ASC
              LIMIT 1'
        );
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $rows);
        } else {
            $statement = $this->pdo->prepare(
                'SELECT COLUMN_NAME FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $statement->execute(['table' => $table]);
            $columns = $statement->fetchAll(PDO::FETCH_COLUMN);
        }
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $identifier) !== 1) {
            throw new \InvalidArgumentException('invalid identifier');
        }
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? '"' . $identifier . '"'
            : '`' . $identifier . '`';
    }
}
