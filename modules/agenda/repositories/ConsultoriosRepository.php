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
        if (!$this->tableExists('consultorios')) {
            $this->createTableIfMissing();
        }
        if (!$this->tableExists('consultorios')) {
            throw new RuntimeException('consultorios table not ready');
        }
        $this->ensureGroupIdSupport();
        $this->ensureGeocodeColumns();
        $this->ensureImageColumnsCapacity();
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $logoUrlIsSet = (int)($payload['logo_url_is_set'] ?? 1);
        $fotoUrlIsSet = (int)($payload['foto_url_is_set'] ?? 1);
        $latIsSet = (int)($payload['lat_is_set'] ?? 1);
        $lngIsSet = (int)($payload['lng_is_set'] ?? 1);
        $geocodeSourceIsSet = (int)($payload['geocode_source_is_set'] ?? 1);
        $geocodeUpdatedAtIsSet = (int)($payload['geocode_updated_at_is_set'] ?? 0);
        $sql = 'INSERT INTO consultorios (
            doctor_id, consultorio_id, group_id, titulo, grupo_nombre,
            calle, num_ext, num_int, cp, colonia, municipio, estado,
            telefonos_json, whatsapp, urgencias_json, logo_url, foto_url,
            lat, lng, geocode_source, geocode_updated_at, updated_at
        ) VALUES (
            :doctor_id, :consultorio_id, :group_id, :titulo, :grupo_nombre,
            :calle, :num_ext, :num_int, :cp, :colonia, :municipio, :estado,
            :telefonos_json, :whatsapp, :urgencias_json, :logo_url, :foto_url,
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
            'logo_url_is_set' => $logoUrlIsSet,
            'foto_url_is_set' => $fotoUrlIsSet,
            'lat_is_set' => $latIsSet,
            'lng_is_set' => $lngIsSet,
            'geocode_source_is_set' => $geocodeSourceIsSet,
            'geocode_updated_at_is_set' => $geocodeUpdatedAtIsSet,
        ]);
    }

    public function updateGroupSnapshot(
        string $doctorId,
        string $consultorioId,
        ?string $groupId,
        ?string $grupoNombre,
        ?string $logoUrl
    ): void {
        $this->ensureTable();
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

    private function createTableIfMissing(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS consultorios (
            doctor_id VARCHAR(64) NOT NULL,
            consultorio_id VARCHAR(64) NOT NULL,
            group_id VARCHAR(64) DEFAULT NULL,
            titulo VARCHAR(190) DEFAULT NULL,
            grupo_nombre VARCHAR(190) DEFAULT NULL,
            calle VARCHAR(190) DEFAULT NULL,
            num_ext VARCHAR(32) DEFAULT NULL,
            num_int VARCHAR(32) DEFAULT NULL,
            cp VARCHAR(16) DEFAULT NULL,
            colonia VARCHAR(120) DEFAULT NULL,
            municipio VARCHAR(120) DEFAULT NULL,
            estado VARCHAR(120) DEFAULT NULL,
            telefonos_json JSON DEFAULT NULL,
            whatsapp VARCHAR(32) DEFAULT NULL,
            urgencias_json JSON DEFAULT NULL,
            logo_url TEXT DEFAULT NULL,
            foto_url TEXT DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            geocode_source VARCHAR(32) DEFAULT NULL,
            geocode_updated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (doctor_id, consultorio_id),
            KEY idx_consultorios_doctor (doctor_id),
            KEY idx_consultorios_group_id (group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
    }

    private function ensureGroupIdSupport(): void
    {
        if (!$this->columnExists('consultorios', 'group_id')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD COLUMN group_id VARCHAR(64) DEFAULT NULL AFTER consultorio_id');
        }
        if (!$this->indexExists('consultorios', 'idx_consultorios_group_id')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD KEY idx_consultorios_group_id (group_id)');
        }
    }

    private function ensureGeocodeColumns(): void
    {
        if (!$this->columnExists('consultorios', 'lat')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER foto_url');
        }
        if (!$this->columnExists('consultorios', 'lng')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat');
        }
        if (!$this->columnExists('consultorios', 'geocode_source')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD COLUMN geocode_source VARCHAR(32) DEFAULT NULL AFTER lng');
        }
        if (!$this->columnExists('consultorios', 'geocode_updated_at')) {
            $this->pdo->exec('ALTER TABLE consultorios ADD COLUMN geocode_updated_at DATETIME DEFAULT NULL AFTER geocode_source');
        }
    }

    private function ensureImageColumnsCapacity(): void
    {
        $logoType = $this->columnDataType('consultorios', 'logo_url');
        if ($logoType !== '' && strtolower($logoType) !== 'longtext') {
            $this->pdo->exec('ALTER TABLE consultorios MODIFY COLUMN logo_url LONGTEXT DEFAULT NULL');
        }
        $fotoType = $this->columnDataType('consultorios', 'foto_url');
        if ($fotoType !== '' && strtolower($fotoType) !== 'longtext') {
            $this->pdo->exec('ALTER TABLE consultorios MODIFY COLUMN foto_url LONGTEXT DEFAULT NULL');
        }
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
