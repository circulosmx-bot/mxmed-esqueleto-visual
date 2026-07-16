export type StorageBucketClassification = 'public' | 'sensitive' | 'clinical';
export type StorageBucketPurpose =
  'public-media' | 'private-documents' | 'clinical-records' | 'upload-quarantine';
export type StorageScanStatus = 'pending' | 'clean' | 'infected' | 'failed';
export type StorageProcessingStatus = 'pending' | 'processing' | 'ready' | 'rejected' | 'failed';
export type StorageMimeProfile = 'public' | 'private' | 'clinical';
export type StorageSizeProfile = 'public-upload' | 'public-derived' | 'private' | 'clinical';

export type StorageMetadataKey =
  'upload-id' | 'checksum-sha256' | 'normalized-content-type' | 'schema-version' | 'source-type';

export type StorageTagKey =
  'classification' | 'scan-status' | 'retention-class' | 'processing-status';

export type StorageObjectMetadata = Readonly<Partial<Record<StorageMetadataKey, string>>>;
export type StorageObjectTags = Readonly<Partial<Record<StorageTagKey, string>>>;

export interface StorageBucketInventory<TBucket> {
  readonly publicMediaBucket: TBucket;
  readonly privateDocumentsBucket: TBucket;
  readonly clinicalRecordsBucket: TBucket;
  readonly uploadQuarantineBucket: TBucket;
}

export interface StorageLifecycleContract {
  readonly abortIncompleteMultipartUploadDays: 1;
  readonly publicMediaNoncurrentRetentionDays: number;
  readonly privateDocumentsNoncurrentRetentionDays: number | null;
  readonly clinicalNoncurrentRetentionDays: number | null;
  readonly quarantineRetentionDays: Readonly<Record<StorageScanStatus, number>>;
  readonly temporaryExportRetentionDays: number;
  readonly privateStorageTransitionDays: number | null;
  readonly clinicalStorageTransitionDays: number | null;
}

export class StorageContractError extends Error {
  public constructor(
    public readonly code: string,
    public readonly field: string,
    public readonly rule: string,
  ) {
    super(`${code}:${field}:${rule}`);
    this.name = 'StorageContractError';
  }
}

export const STORAGE_ALLOWED_MIME_TYPES = Object.freeze({
  public: Object.freeze(['image/jpeg', 'image/png', 'image/webp']),
  private: Object.freeze(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
  clinical: Object.freeze(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
} as const);

export const STORAGE_BUCKET_CLASSIFICATION_MAP = Object.freeze({
  'public-media': 'public',
  'private-documents': 'sensitive',
  'clinical-records': 'clinical',
  'upload-quarantine': 'sensitive',
} as const satisfies Readonly<Record<StorageBucketPurpose, StorageBucketClassification>>);

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const TECHNICAL_VALUE_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const CHECKSUM_SHA256_PATTERN = /^[0-9a-f]{64}$/;
const SCHEMA_VERSION_PATTERN = /^v?[1-9][0-9]{0,3}$/;
const FORBIDDEN_SEMANTIC_PATTERN =
  /patient|paciente|doctor|medico|m[eé]dico|filename|nombre|diagnos|email|phone|telefono|tel[eé]fono|curp|birth|nacimiento/i;
const PUBLIC_MEDIA_VARIANTS = new Set([
  'optimized',
  'thumbnail',
  'small',
  'medium',
  'large',
  'profile',
  'logo',
  'square',
]);
const PUBLIC_MEDIA_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp']);
const METADATA_KEYS = new Set<StorageMetadataKey>([
  'upload-id',
  'checksum-sha256',
  'normalized-content-type',
  'schema-version',
  'source-type',
]);
const TAG_KEYS = new Set<StorageTagKey>([
  'classification',
  'scan-status',
  'retention-class',
  'processing-status',
]);
const CLASSIFICATIONS = new Set<StorageBucketClassification>(['public', 'sensitive', 'clinical']);
const SCAN_STATUSES = new Set<StorageScanStatus>(['pending', 'clean', 'infected', 'failed']);
const PROCESSING_STATUSES = new Set<StorageProcessingStatus>([
  'pending',
  'processing',
  'ready',
  'rejected',
  'failed',
]);
const MAX_SIZE_MIB: Readonly<Record<StorageSizeProfile, number>> = Object.freeze({
  'public-upload': 20,
  'public-derived': 10,
  private: 100,
  clinical: 100,
});
const MIB = 1024 * 1024;

function hasControlCharacter(value: string): boolean {
  for (let index = 0; index < value.length; index += 1) {
    const codeUnit = value.charCodeAt(index);
    if (codeUnit <= 31 || codeUnit === 127) return true;
  }
  return false;
}

function fail(field: string, rule: string): never {
  throw new StorageContractError('MXMED_STORAGE_CONTRACT_INVALID', field, rule);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function validateUuid(value: string, field: string): void {
  if (
    !UUID_PATTERN.test(value) ||
    value.includes('/') ||
    value.includes('..') ||
    value.includes('?') ||
    value.includes('#') ||
    hasControlCharacter(value)
  ) {
    fail(field, 'must be a canonical opaque UUID');
  }
}

function validateTechnicalValue(value: string, field: string): void {
  if (
    value.length === 0 ||
    value.length > 128 ||
    !TECHNICAL_VALUE_PATTERN.test(value) ||
    FORBIDDEN_SEMANTIC_PATTERN.test(value) ||
    hasControlCharacter(value)
  ) {
    fail(field, 'must be an allowlisted technical value');
  }
}

export function buildQuarantineObjectKey(uploadUuid: string): string {
  validateUuid(uploadUuid, 'uploadUuid');
  return `uploads/${uploadUuid}/source`;
}

export function buildPublicMediaObjectKey(
  assetUuid: string,
  variant: string,
  extension: string,
): string {
  validateUuid(assetUuid, 'assetUuid');
  if (!PUBLIC_MEDIA_VARIANTS.has(variant)) fail('variant', 'must be an approved public variant');
  if (!PUBLIC_MEDIA_EXTENSIONS.has(extension)) {
    fail('extension', 'must be jpg, jpeg, png or webp');
  }
  return `assets/${assetUuid}/${variant}.${extension}`;
}

export function buildPrivateDocumentObjectKey(objectUuid: string): string {
  validateUuid(objectUuid, 'objectUuid');
  return `objects/${objectUuid}`;
}

export function buildClinicalRecordObjectKey(objectUuid: string): string {
  validateUuid(objectUuid, 'objectUuid');
  return `records/${objectUuid}`;
}

export function buildTemporaryExportObjectKey(exportUuid: string): string {
  validateUuid(exportUuid, 'exportUuid');
  return `temporary-exports/${exportUuid}`;
}

export function validateStorageObjectMetadata(
  input: unknown,
): asserts input is StorageObjectMetadata {
  if (!isRecord(input)) fail('metadata', 'must be an object');
  for (const [key, rawValue] of Object.entries(input)) {
    if (!METADATA_KEYS.has(key as StorageMetadataKey)) fail('metadata', 'contains a forbidden key');
    if (typeof rawValue !== 'string' || hasControlCharacter(rawValue)) {
      fail('metadata', 'values must be safe strings');
    }
    if (key === 'upload-id') validateUuid(rawValue, 'upload-id');
    else if (key === 'checksum-sha256' && !CHECKSUM_SHA256_PATTERN.test(rawValue)) {
      fail('checksum-sha256', 'must be lowercase SHA-256 hex');
    } else if (key === 'normalized-content-type') {
      const allowed = Object.values(STORAGE_ALLOWED_MIME_TYPES).flat();
      if (!allowed.includes(rawValue)) fail(key, 'must be an allowed normalized MIME');
    } else if (key === 'schema-version' && !SCHEMA_VERSION_PATTERN.test(rawValue)) {
      fail(key, 'must be a bounded schema version');
    } else if (key === 'source-type') validateTechnicalValue(rawValue, key);
  }
}

export function validateStorageObjectTags(input: unknown): asserts input is StorageObjectTags {
  if (!isRecord(input)) fail('tags', 'must be an object');
  for (const [key, rawValue] of Object.entries(input)) {
    if (!TAG_KEYS.has(key as StorageTagKey)) fail('tags', 'contains a forbidden key');
    if (typeof rawValue !== 'string') fail('tags', 'values must be strings');
    if (
      (key === 'classification' && !CLASSIFICATIONS.has(rawValue as StorageBucketClassification)) ||
      (key === 'scan-status' && !SCAN_STATUSES.has(rawValue as StorageScanStatus)) ||
      (key === 'processing-status' && !PROCESSING_STATUSES.has(rawValue as StorageProcessingStatus))
    ) {
      fail(key, 'contains an unsupported value');
    }
    if (key === 'retention-class') validateTechnicalValue(rawValue, key);
  }
}

export function validateStorageMimeType(profile: StorageMimeProfile, mimeType: string): void {
  if (!STORAGE_ALLOWED_MIME_TYPES[profile].includes(mimeType)) {
    fail('mimeType', 'is not allowed for the selected storage profile');
  }
}

export function validateStorageObjectSize(profile: StorageSizeProfile, bytes: number): void {
  if (!Number.isInteger(bytes) || bytes <= 0 || bytes > MAX_SIZE_MIB[profile] * MIB) {
    fail('bytes', 'must be a positive integer within the selected size ceiling');
  }
}

export function validateUploadTtl(seconds: number): void {
  if (!Number.isInteger(seconds) || seconds <= 0 || seconds > 600) {
    fail('uploadUrlTtlSeconds', 'must be a positive integer no greater than 600');
  }
}

export function validateDownloadTtl(seconds: number): void {
  if (!Number.isInteger(seconds) || seconds <= 0 || seconds > 300) {
    fail('downloadUrlTtlSeconds', 'must be a positive integer no greater than 300');
  }
}
