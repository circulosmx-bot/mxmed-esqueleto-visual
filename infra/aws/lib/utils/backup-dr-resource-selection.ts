const FORBIDDEN_SELECTION = /(?:\*|PublicMedia|UploadQuarantine|AuditBucket)/i;

export function assertExplicitBackupResources(
  resources: readonly string[],
  expectedCount: number,
): void {
  if (
    resources.length !== expectedCount ||
    new Set(resources).size !== expectedCount ||
    resources.some((resource) => FORBIDDEN_SELECTION.test(resource))
  ) {
    throw new Error('MXMED_BACKUP_RESOURCE_SELECTION_INVALID');
  }
}

export function assertQuarantineExcluded(resources: readonly string[]): void {
  if (resources.some((resource) => /quarantine/i.test(resource))) {
    throw new Error('quarantine_bucket_backup_forbidden');
  }
}
