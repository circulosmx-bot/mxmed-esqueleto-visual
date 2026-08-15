<?php
declare(strict_types=1);

namespace Platform\Audit\Db;

enum AuditDatabaseCredentialRole
{
    case MIGRATION;
    case WRITER;
    case READER;

    public function service(): string
    {
        return match ($this) {
            self::MIGRATION => 'mxmed.audit.db.migration.local',
            self::WRITER => 'mxmed.audit.db.writer.local',
            self::READER => 'mxmed.audit.db.reader.local',
        };
    }

    public function account(): string
    {
        return match ($this) {
            self::MIGRATION => 'mxmed_audit_migration_local',
            self::WRITER => 'mxmed_audit_writer_local',
            self::READER => 'mxmed_audit_reader_local',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MIGRATION => 'MXMed Audit DB Migration Local',
            self::WRITER => 'MXMed Audit DB Writer Local',
            self::READER => 'MXMed Audit DB Reader Local',
        };
    }

    public function host(): string
    {
        return '127.0.0.1';
    }
}
