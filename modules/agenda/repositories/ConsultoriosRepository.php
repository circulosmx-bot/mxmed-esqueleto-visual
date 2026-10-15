<?php
namespace Agenda\Repositories;

use PDO;
use RuntimeException;

class ConsultoriosRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function ensureTable(): void
    {
        $requiredColumns = [
            'group_id',
            'lat',
            'lng',
            'geocode_source',
            'geocode_updated_at',
            'logo_url',
            'foto_url',
            'document_is_primary',
            'document_display_order',
            'document_publication_enabled',
            'document_primary_slot',
            'local_parking',
            'accessible',
        ];
        if (!$this->tableExists('consultorios')) {
            throw new RuntimeException('schema_not_ready');
        }
        foreach ($requiredColumns as $column) {
            if (!$this->columnExists('consultorios', $column)) {
                throw new RuntimeException('schema_not_ready');
            }
        }
        if (!$this->indexExists('consultorios', 'idx_consultorios_group_id')
            || !$this->indexExists('consultorios', 'uq_consultorios_document_primary')
            || !$this->indexExists('consultorios', 'uq_consultorios_document_order')
            || !$this->indexExists('consultorios', 'idx_consultorios_document_footer')
            || strtolower($this->columnDataType('consultorios', 'logo_url')) !== 'longtext'
            || strtolower($this->columnDataType('consultorios', 'foto_url')) !== 'longtext') {
            throw new RuntimeException('schema_not_ready');
        }
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listByDoctor(string $doctorId): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM consultorios WHERE doctor_id = :doctor_id ORDER BY consultorio_id ASC');
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $state = $this->documentPresentationStateFromRows($rows);
        if ($state['configured']) {
            usort($rows, static fn(array $left, array $right): int =>
                ((int)$left['document_display_order'] <=> (int)$right['document_display_order'])
                ?: strcmp((string)$left['consultorio_id'], (string)$right['consultorio_id']));
        }
        return $rows;
    }

    /** @return array{configured:bool,order:list<string>,fingerprint:string} */
    public function documentPresentationState(string $doctorId, ?array $rows = null): array
    {
        return $this->documentPresentationStateFromRows($rows ?? $this->listByDoctor($doctorId));
    }

    /**
     * Canonically replace one physician's complete office order.
     * Publication consent is deliberately outside this operation.
     *
     * @param list<string> $orderedConsultorioIds
     * @return array{configured:bool,order:list<string>,fingerprint:string}
     */
    public function reorderDocumentPresentation(
        string $doctorId,
        array $orderedConsultorioIds,
        string $expectedFingerprint
    ): array {
        $this->ensureTable();
        $doctorId = trim($doctorId);
        $orderedConsultorioIds = array_values(array_map(static fn($id): string => trim((string)$id), $orderedConsultorioIds));
        if ($doctorId === '' || $orderedConsultorioIds === [] || count($orderedConsultorioIds) > 3
            || in_array('', $orderedConsultorioIds, true)
            || count(array_unique($orderedConsultorioIds)) !== count($orderedConsultorioIds)
            || preg_match('/^[a-f0-9]{64}$/', $expectedFingerprint) !== 1) {
            throw new RuntimeException('invalid_consultorio_order');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare(
                'SELECT * FROM consultorios
                  WHERE doctor_id = :doctor_id
                  ORDER BY consultorio_id ASC
                  FOR UPDATE'
            );
            $lock->execute(['doctor_id' => $doctorId]);
            $currentRows = $lock->fetchAll(PDO::FETCH_ASSOC);
            $currentState = $this->documentPresentationStateFromRows($currentRows);
            if (!hash_equals($currentState['fingerprint'], $expectedFingerprint)) {
                throw new RuntimeException('stale_consultorio_order');
            }
            $currentIds = array_map(static fn(array $row): string => (string)$row['consultorio_id'], $currentRows);
            $requestedIds = $orderedConsultorioIds;
            sort($currentIds, SORT_STRING);
            sort($requestedIds, SORT_STRING);
            if ($currentIds !== $requestedIds) {
                throw new RuntimeException('consultorio_order_scope_mismatch');
            }

            if (!$currentState['configured'] || $currentState['order'] !== $orderedConsultorioIds) {
                $clear = $this->pdo->prepare(
                    'UPDATE consultorios
                        SET document_is_primary = 0, document_display_order = NULL
                      WHERE doctor_id = :doctor_id'
                );
                $clear->execute(['doctor_id' => $doctorId]);

                $orderCases = [];
                $idPlaceholders = [];
                $params = ['doctor_id' => $doctorId, 'primary_id' => $orderedConsultorioIds[0]];
                foreach ($orderedConsultorioIds as $index => $consultorioId) {
                    $idKey = 'office_' . $index;
                    $scopeKey = 'scope_office_' . $index;
                    $orderKey = 'order_' . $index;
                    $orderCases[] = "WHEN :{$idKey} THEN :{$orderKey}";
                    $idPlaceholders[] = ":{$scopeKey}";
                    $params[$idKey] = $consultorioId;
                    $params[$scopeKey] = $consultorioId;
                    $params[$orderKey] = $index + 1;
                }
                $sql = 'UPDATE consultorios
                           SET document_is_primary = CASE WHEN consultorio_id = :primary_id THEN 1 ELSE 0 END,
                               document_display_order = CASE consultorio_id ' . implode(' ', $orderCases) . ' ELSE NULL END,
                               updated_at = NOW()
                         WHERE doctor_id = :doctor_id
                           AND consultorio_id IN (' . implode(',', $idPlaceholders) . ')';
                $this->pdo->prepare($sql)->execute($params);
            }

            $after = $this->pdo->prepare(
                'SELECT * FROM consultorios WHERE doctor_id = :doctor_id ORDER BY consultorio_id ASC FOR UPDATE'
            );
            $after->execute(['doctor_id' => $doctorId]);
            $state = $this->documentPresentationStateFromRows($after->fetchAll(PDO::FETCH_ASSOC));
            if (!$state['configured'] || $state['order'] !== $orderedConsultorioIds) {
                throw new RuntimeException('consultorio_order_invariant_failed');
            }
            if ($ownsTransaction) $this->pdo->commit();
            return $state;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array{configured:bool,order:list<string>,fingerprint:string} */
    private function documentPresentationStateFromRows(array $rows): array
    {
        usort($rows, static fn(array $left, array $right): int =>
            strcmp((string)($left['consultorio_id'] ?? ''), (string)($right['consultorio_id'] ?? '')));
        $fingerprintParts = [];
        $ordered = [];
        $primaryCount = 0;
        foreach ($rows as $row) {
            $id = trim((string)($row['consultorio_id'] ?? ''));
            $primary = (int)($row['document_is_primary'] ?? 0);
            $order = $row['document_display_order'] === null ? null : (int)$row['document_display_order'];
            $fingerprintParts[] = $id . ':' . $primary . ':' . ($order === null ? 'null' : (string)$order);
            if ($primary === 1) $primaryCount++;
            if ($order !== null) $ordered[$order] = $id;
        }
        ksort($ordered, SORT_NUMERIC);
        $orderedIds = array_values($ordered);
        $configured = $rows !== []
            && $primaryCount === 1
            && count($orderedIds) === count($rows)
            && array_keys($ordered) === range(1, count($rows));
        if ($configured) {
            $primaryId = '';
            foreach ($rows as $row) {
                if ((int)($row['document_is_primary'] ?? 0) === 1) {
                    $primaryId = (string)$row['consultorio_id'];
                    break;
                }
            }
            $configured = $orderedIds[0] === $primaryId;
        }
        $fallback = array_map(static fn(array $row): string => (string)$row['consultorio_id'], $rows);
        return [
            'configured' => $configured,
            'order' => $configured ? $orderedIds : $fallback,
            'fingerprint' => hash('sha256', implode('|', $fingerprintParts)),
        ];
    }

    public function getByDoctorConsultorio(string $doctorId, string $consultorioId): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM consultorios WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id LIMIT 1'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function upsertConsultorio(array $payload): void
    {
        $this->ensureTable();
        if (($payload['group_id_is_set'] ?? 1) && trim((string)($payload['group_id'] ?? '')) !== '') {
            $this->assertMedicalGroup((string)$payload['group_id']);
        }
        $presentationIsSet = (int)($payload['document_is_primary_is_set'] ?? 0) === 1
            || (int)($payload['document_display_order_is_set'] ?? 0) === 1
            || (int)($payload['document_publication_enabled_is_set'] ?? 0) === 1;
        $ownsTransaction = false;
        if ($presentationIsSet && !$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTransaction = true;
        }
        if ($presentationIsSet) {
            try {
                $lock = $this->pdo->prepare(
                    'SELECT consultorio_id, document_is_primary, document_display_order
                       FROM consultorios
                      WHERE doctor_id = :doctor_id
                      ORDER BY consultorio_id
                      FOR UPDATE'
                );
                $lock->execute(['doctor_id' => (string)$payload['doctor_id']]);
                $lock->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                if ($ownsTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }
        $groupIdIsSet = (int)($payload['group_id_is_set'] ?? 1);
        $tituloIsSet = (int)($payload['titulo_is_set'] ?? 1);
        $grupoNombreIsSet = (int)($payload['grupo_nombre_is_set'] ?? 1);
        $calleIsSet = (int)($payload['calle_is_set'] ?? 1);
        $numExtIsSet = (int)($payload['num_ext_is_set'] ?? 1);
        $numIntIsSet = (int)($payload['num_int_is_set'] ?? 1);
        $cpIsSet = (int)($payload['cp_is_set'] ?? 1);
        $coloniaIsSet = (int)($payload['colonia_is_set'] ?? 1);
        $municipioIsSet = (int)($payload['municipio_is_set'] ?? 1);
        $estadoIsSet = (int)($payload['estado_is_set'] ?? 1);
        $telefonosIsSet = (int)($payload['telefonos_json_is_set'] ?? 1);
        $whatsappIsSet = (int)($payload['whatsapp_is_set'] ?? 1);
        $urgenciasIsSet = (int)($payload['urgencias_json_is_set'] ?? 1);
        $localParkingIsSet = (int)($payload['local_parking_is_set'] ?? 0);
        $accessibleIsSet = (int)($payload['accessible_is_set'] ?? 0);
        $logoUrlIsSet = (int)($payload['logo_url_is_set'] ?? 1);
        $fotoUrlIsSet = (int)($payload['foto_url_is_set'] ?? 1);
        $latIsSet = (int)($payload['lat_is_set'] ?? 1);
        $lngIsSet = (int)($payload['lng_is_set'] ?? 1);
        $geocodeSourceIsSet = (int)($payload['geocode_source_is_set'] ?? 1);
        $geocodeUpdatedAtIsSet = (int)($payload['geocode_updated_at_is_set'] ?? 0);
        $sql = 'INSERT INTO consultorios (
            doctor_id, consultorio_id, group_id, titulo, grupo_nombre,
            calle, num_ext, num_int, cp, colonia, municipio, estado,
            telefonos_json, whatsapp, urgencias_json, local_parking, `accessible`, logo_url, foto_url,
            lat, lng, geocode_source, geocode_updated_at, updated_at
        ) VALUES (
            :doctor_id, :consultorio_id, :group_id, :titulo, :grupo_nombre,
            :calle, :num_ext, :num_int, :cp, :colonia, :municipio, :estado,
            :telefonos_json, :whatsapp, :urgencias_json, :local_parking, :accessible, :logo_url, :foto_url,
            :lat, :lng, :geocode_source,
            CASE
                WHEN :geocode_updated_at_is_set = 1 THEN :geocode_updated_at
                WHEN :lat IS NOT NULL AND :lng IS NOT NULL THEN NOW()
                ELSE NULL
            END,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            group_id = CASE WHEN :group_id_is_set = 1 THEN VALUES(group_id) ELSE group_id END,
            titulo = CASE WHEN :titulo_is_set = 1 THEN VALUES(titulo) ELSE titulo END,
            grupo_nombre = CASE WHEN :grupo_nombre_is_set = 1 THEN VALUES(grupo_nombre) ELSE grupo_nombre END,
            calle = CASE WHEN :calle_is_set = 1 THEN VALUES(calle) ELSE calle END,
            num_ext = CASE WHEN :num_ext_is_set = 1 THEN VALUES(num_ext) ELSE num_ext END,
            num_int = CASE WHEN :num_int_is_set = 1 THEN VALUES(num_int) ELSE num_int END,
            cp = CASE WHEN :cp_is_set = 1 THEN VALUES(cp) ELSE cp END,
            colonia = CASE WHEN :colonia_is_set = 1 THEN VALUES(colonia) ELSE colonia END,
            municipio = CASE WHEN :municipio_is_set = 1 THEN VALUES(municipio) ELSE municipio END,
            estado = CASE WHEN :estado_is_set = 1 THEN VALUES(estado) ELSE estado END,
            telefonos_json = CASE WHEN :telefonos_json_is_set = 1 THEN VALUES(telefonos_json) ELSE telefonos_json END,
            whatsapp = CASE WHEN :whatsapp_is_set = 1 THEN VALUES(whatsapp) ELSE whatsapp END,
            urgencias_json = CASE WHEN :urgencias_json_is_set = 1 THEN VALUES(urgencias_json) ELSE urgencias_json END,
            local_parking = CASE WHEN :local_parking_is_set = 1 THEN VALUES(local_parking) ELSE local_parking END,
            `accessible` = CASE WHEN :accessible_is_set = 1 THEN VALUES(`accessible`) ELSE `accessible` END,
            logo_url = CASE WHEN :logo_url_is_set = 1 THEN VALUES(logo_url) ELSE logo_url END,
            foto_url = CASE WHEN :foto_url_is_set = 1 THEN VALUES(foto_url) ELSE foto_url END,
            lat = CASE
                WHEN :lat_is_set = 1
                     AND :geocode_updated_at_is_set = 1
                     AND consultorios.geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at < consultorios.geocode_updated_at
                    THEN lat
                WHEN :lat_is_set = 1 THEN VALUES(lat)
                ELSE lat
            END,
            lng = CASE
                WHEN :lng_is_set = 1
                     AND :geocode_updated_at_is_set = 1
                     AND consultorios.geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at < consultorios.geocode_updated_at
                    THEN lng
                WHEN :lng_is_set = 1 THEN VALUES(lng)
                ELSE lng
            END,
            geocode_source = CASE
                WHEN :geocode_source_is_set = 1
                     AND :geocode_updated_at_is_set = 1
                     AND consultorios.geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at < consultorios.geocode_updated_at
                    THEN geocode_source
                WHEN :geocode_source_is_set = 1 THEN VALUES(geocode_source)
                ELSE geocode_source
            END,
            geocode_updated_at = CASE
                WHEN :geocode_updated_at_is_set = 1
                     AND consultorios.geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at IS NOT NULL
                     AND :geocode_updated_at < consultorios.geocode_updated_at
                    THEN geocode_updated_at
                WHEN :geocode_updated_at_is_set = 1 THEN :geocode_updated_at
                WHEN (:lat_is_set = 1 OR :lng_is_set = 1 OR :geocode_source_is_set = 1)
                     AND VALUES(lat) IS NOT NULL AND VALUES(lng) IS NOT NULL THEN NOW()
                WHEN (:lat_is_set = 1 OR :lng_is_set = 1) AND (VALUES(lat) IS NULL OR VALUES(lng) IS NULL) THEN NULL
                ELSE geocode_updated_at
            END,
            updated_at = NOW()';
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
            'doctor_id' => (string)$payload['doctor_id'],
            'consultorio_id' => (string)$payload['consultorio_id'],
            'group_id' => $payload['group_id'] ?? null,
            'titulo' => $payload['titulo'] ?? null,
            'grupo_nombre' => $payload['grupo_nombre'] ?? null,
            'calle' => $payload['calle'] ?? null,
            'num_ext' => $payload['num_ext'] ?? null,
            'num_int' => $payload['num_int'] ?? null,
            'cp' => $payload['cp'] ?? null,
            'colonia' => $payload['colonia'] ?? null,
            'municipio' => $payload['municipio'] ?? null,
            'estado' => $payload['estado'] ?? null,
            'telefonos_json' => $payload['telefonos_json'] ?? null,
            'whatsapp' => $payload['whatsapp'] ?? null,
            'urgencias_json' => $payload['urgencias_json'] ?? null,
            'local_parking' => $payload['local_parking'] ?? null,
            'accessible' => $payload['accessible'] ?? null,
            'logo_url' => $payload['logo_url'] ?? null,
            'foto_url' => $payload['foto_url'] ?? null,
            'lat' => $payload['lat'] ?? null,
            'lng' => $payload['lng'] ?? null,
            'geocode_source' => $payload['geocode_source'] ?? null,
            'geocode_updated_at' => $payload['geocode_updated_at'] ?? null,
            'group_id_is_set' => $groupIdIsSet,
            'titulo_is_set' => $tituloIsSet,
            'grupo_nombre_is_set' => $grupoNombreIsSet,
            'calle_is_set' => $calleIsSet,
            'num_ext_is_set' => $numExtIsSet,
            'num_int_is_set' => $numIntIsSet,
            'cp_is_set' => $cpIsSet,
            'colonia_is_set' => $coloniaIsSet,
            'municipio_is_set' => $municipioIsSet,
            'estado_is_set' => $estadoIsSet,
            'telefonos_json_is_set' => $telefonosIsSet,
            'whatsapp_is_set' => $whatsappIsSet,
            'urgencias_json_is_set' => $urgenciasIsSet,
            'local_parking_is_set' => $localParkingIsSet,
            'accessible_is_set' => $accessibleIsSet,
            'logo_url_is_set' => $logoUrlIsSet,
            'foto_url_is_set' => $fotoUrlIsSet,
            'lat_is_set' => $latIsSet,
            'lng_is_set' => $lngIsSet,
            'geocode_source_is_set' => $geocodeSourceIsSet,
                'geocode_updated_at_is_set' => $geocodeUpdatedAtIsSet,
            ]);
            if ($presentationIsSet) {
                $this->applyDocumentPresentation($payload);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Apply document presentation decisions while the physician's consultorio rows
     * are locked. This method deliberately has no implicit-primary fallback.
     */
    private function applyDocumentPresentation(array $payload): void
    {
        $doctorId = (string)$payload['doctor_id'];
        $consultorioId = (string)$payload['consultorio_id'];

        if ((int)($payload['document_display_order_is_set'] ?? 0) === 1) {
            $current = $this->pdo->prepare(
                'SELECT document_display_order FROM consultorios
                  WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id LIMIT 1'
            );
            $current->execute(['doctor_id' => $doctorId, 'consultorio_id' => $consultorioId]);
            $oldOrder = $current->fetchColumn();
            $oldOrder = $oldOrder === false || $oldOrder === null ? null : (int)$oldOrder;
            $newOrder = $payload['document_display_order'] ?? null;
            $newOrder = $newOrder === null ? null : (int)$newOrder;

            if ($newOrder !== $oldOrder) {
                $clearTarget = $this->pdo->prepare(
                    'UPDATE consultorios SET document_display_order = NULL
                      WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id'
                );
                $clearTarget->execute(['doctor_id' => $doctorId, 'consultorio_id' => $consultorioId]);
                if ($newOrder !== null) {
                    $swap = $this->pdo->prepare(
                        'UPDATE consultorios SET document_display_order = :old_order
                          WHERE doctor_id = :doctor_id
                            AND consultorio_id <> :consultorio_id
                            AND document_display_order = :new_order'
                    );
                    $swap->bindValue(':old_order', $oldOrder, $oldOrder === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $swap->bindValue(':doctor_id', $doctorId);
                    $swap->bindValue(':consultorio_id', $consultorioId);
                    $swap->bindValue(':new_order', $newOrder, PDO::PARAM_INT);
                    $swap->execute();
                    $setOrder = $this->pdo->prepare(
                        'UPDATE consultorios SET document_display_order = :new_order
                          WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id'
                    );
                    $setOrder->execute([
                        'new_order' => $newOrder,
                        'doctor_id' => $doctorId,
                        'consultorio_id' => $consultorioId,
                    ]);
                }
            }
        }

        if ((int)($payload['document_is_primary_is_set'] ?? 0) === 1) {
            if ((int)($payload['document_is_primary'] ?? 0) === 1) {
                $clearPrimary = $this->pdo->prepare(
                    'UPDATE consultorios SET document_is_primary = 0
                      WHERE doctor_id = :doctor_id AND document_is_primary = 1'
                );
                $clearPrimary->execute(['doctor_id' => $doctorId]);
            }
            $setPrimary = $this->pdo->prepare(
                'UPDATE consultorios SET document_is_primary = :is_primary
                  WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id'
            );
            $setPrimary->execute([
                'is_primary' => (int)($payload['document_is_primary'] ?? 0),
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
            ]);
        }

        if ((int)($payload['document_publication_enabled_is_set'] ?? 0) === 1) {
            $publish = $this->pdo->prepare(
                'UPDATE consultorios SET document_publication_enabled = :enabled
                  WHERE doctor_id = :doctor_id AND consultorio_id = :consultorio_id'
            );
            $publish->execute([
                'enabled' => (int)($payload['document_publication_enabled'] ?? 0),
                'doctor_id' => $doctorId,
                'consultorio_id' => $consultorioId,
            ]);
        }
    }

    public function updateGroupSnapshot(
        string $doctorId,
        string $consultorioId,
        ?string $groupId,
        ?string $grupoNombre,
        ?string $logoUrl
    ): void {
        $this->ensureTable();
        if ($groupId !== null && trim($groupId) !== '') {
            $this->assertMedicalGroup($groupId);
        }
        $existing = $this->getByDoctorConsultorio($doctorId, $consultorioId);
        if (!is_array($existing)) {
            throw new RuntimeException('consultorio_not_found');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE consultorios
                SET group_id = :group_id,
                    grupo_nombre = :grupo_nombre,
                    logo_url = :logo_url,
                    updated_at = NOW()
              WHERE doctor_id = :doctor_id
                AND consultorio_id = :consultorio_id'
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'group_id' => $groupId,
            'grupo_nombre' => $grupoNombre,
            'logo_url' => $logoUrl,
        ]);
    }

    public function updateGroupSnapshotByGroupId(
        string $groupId,
        ?string $nextGroupId,
        ?string $grupoNombre,
        ?string $logoUrl
    ): int {
        $this->ensureTable();
        if ($nextGroupId !== null && trim($nextGroupId) !== '') {
            $this->assertMedicalGroup($nextGroupId);
        }
        $groupId = trim($groupId);
        if ($groupId === '') {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE consultorios
                SET group_id = :next_group_id,
                    grupo_nombre = :grupo_nombre,
                    logo_url = :logo_url,
                    updated_at = NOW()
              WHERE group_id = :group_id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'next_group_id' => $nextGroupId,
            'grupo_nombre' => $grupoNombre,
            'logo_url' => $logoUrl,
        ]);
        return $stmt->rowCount();
    }

    private function columnDataType(string $table, string $column): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATA_TYPE
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = :table
                AND column_name = :column
              LIMIT 1'
        );
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);
        $type = $stmt->fetchColumn();
        return is_string($type) ? trim($type) : '';
    }

    private function assertMedicalGroup(string $groupId): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM medical_groups WHERE group_id = :group_id AND organization_type_key = 'MEDICAL_GROUP' LIMIT 1");
        $stmt->execute(['group_id' => trim($groupId)]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('medical_group_type_required');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :idx'
        );
        $stmt->execute([
            'table' => $table,
            'idx' => $index,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
