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
        $sql = 'INSERT INTO consultorios (
            doctor_id, consultorio_id, titulo, grupo_nombre,
            calle, num_ext, num_int, cp, colonia, municipio, estado,
            telefonos_json, whatsapp, urgencias_json, logo_url, foto_url,
            updated_at
        ) VALUES (
            :doctor_id, :consultorio_id, :titulo, :grupo_nombre,
            :calle, :num_ext, :num_int, :cp, :colonia, :municipio, :estado,
            :telefonos_json, :whatsapp, :urgencias_json, :logo_url, :foto_url,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            titulo = VALUES(titulo),
            grupo_nombre = VALUES(grupo_nombre),
            calle = VALUES(calle),
            num_ext = VALUES(num_ext),
            num_int = VALUES(num_int),
            cp = VALUES(cp),
            colonia = VALUES(colonia),
            municipio = VALUES(municipio),
            estado = VALUES(estado),
            telefonos_json = VALUES(telefonos_json),
            whatsapp = VALUES(whatsapp),
            urgencias_json = VALUES(urgencias_json),
            logo_url = VALUES(logo_url),
            foto_url = VALUES(foto_url),
            updated_at = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'doctor_id' => (string)$payload['doctor_id'],
            'consultorio_id' => (string)$payload['consultorio_id'],
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
        ]);
    }

    private function createTableIfMissing(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS consultorios (
            doctor_id VARCHAR(64) NOT NULL,
            consultorio_id VARCHAR(64) NOT NULL,
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (doctor_id, consultorio_id),
            KEY idx_consultorios_doctor (doctor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
    }
}
