export type SessionEnvironmentCode = 'stg' | 'prd';
export type SessionSameSite = 'Lax';

export interface SessionKeyContract {
  readonly environmentCode: SessionEnvironmentCode;
  readonly prefix: string;
}

export interface SessionPayloadContract {
  readonly targetPayloadKiB: 16;
  readonly maximumPayloadKiB: 32;
  readonly allowedKeys: readonly SessionPayloadKey[];
}

export interface SessionCookieContract {
  readonly name: '__Host-mxmed_session';
  readonly secure: true;
  readonly httpOnly: true;
  readonly sameSite: SessionSameSite;
  readonly path: '/';
  readonly domain: null;
  readonly useStrictMode: true;
  readonly useOnlyCookies: true;
  readonly useTransSid: false;
  readonly gcMaxLifetime: 1800;
  readonly lazyWrite: true;
}

export interface SessionLockContract {
  readonly enabled: true;
  readonly timeoutSeconds: 10;
  readonly waitMicroseconds: 100000;
  readonly maximumWaitSeconds: 10;
  readonly boundedRetries: true;
}

export interface SessionExpirationContract {
  readonly idleTtlSeconds: 1800;
  readonly absoluteLifetimeSeconds: 43200;
  readonly createdAtEpochSeconds: number;
  readonly lastSeenAtEpochSeconds: number;
  readonly absoluteExpiresAtEpochSeconds: number;
}

export interface SessionAclContract {
  readonly environmentCode: SessionEnvironmentCode;
  readonly keyPattern: string;
  readonly allowedCommands: readonly SessionAclCommand[];
}

export type SessionPayloadKey =
  | 'subject_id'
  | 'entity_type'
  | 'entity_id'
  | 'role'
  | 'permission_version'
  | 'authenticated'
  | 'csrf_state'
  | 'created_at'
  | 'last_seen_at'
  | 'absolute_expires_at'
  | 'session_version'
  | 'security_flags';

export type SessionAclCommand =
  | 'get'
  | 'set'
  | 'setex'
  | 'del'
  | 'unlink'
  | 'exists'
  | 'expire'
  | 'pexpire'
  | 'ttl'
  | 'pttl'
  | 'touch'
  | 'ping';

export class SessionContractError extends Error {
  public constructor(
    public readonly code: 'MXMED_SESSION_CONTRACT_INVALID',
    public readonly field: string,
    public readonly rule: string,
  ) {
    super(`${code}:${field}:${rule}`);
    this.name = 'SessionContractError';
  }
}

export const SESSION_PAYLOAD_KEYS = Object.freeze([
  'subject_id',
  'entity_type',
  'entity_id',
  'role',
  'permission_version',
  'authenticated',
  'csrf_state',
  'created_at',
  'last_seen_at',
  'absolute_expires_at',
  'session_version',
  'security_flags',
] as const satisfies readonly SessionPayloadKey[]);

export const SESSION_ACL_COMMANDS = Object.freeze([
  'get',
  'set',
  'setex',
  'del',
  'unlink',
  'exists',
  'expire',
  'pexpire',
  'ttl',
  'pttl',
  'touch',
  'ping',
] as const satisfies readonly SessionAclCommand[]);

export const SESSION_PAYLOAD_CONTRACT: SessionPayloadContract = Object.freeze({
  targetPayloadKiB: 16,
  maximumPayloadKiB: 32,
  allowedKeys: SESSION_PAYLOAD_KEYS,
});

export const SESSION_COOKIE_CONTRACT: SessionCookieContract = Object.freeze({
  name: '__Host-mxmed_session',
  secure: true,
  httpOnly: true,
  sameSite: 'Lax',
  path: '/',
  domain: null,
  useStrictMode: true,
  useOnlyCookies: true,
  useTransSid: false,
  gcMaxLifetime: 1800,
  lazyWrite: true,
});

export const SESSION_LOCK_CONTRACT: SessionLockContract = Object.freeze({
  enabled: true,
  timeoutSeconds: 10,
  waitMicroseconds: 100000,
  maximumWaitSeconds: 10,
  boundedRetries: true,
});

const OPAQUE_SESSION_ID_PATTERN = /^[A-Za-z0-9_-]{32,128}$/;
const IDENTIFYING_ID_PATTERN = /^(?:user|doctor|medic|patient|email|name|tenant)[_-]/i;
const FORBIDDEN_PAYLOAD_KEY_PATTERN =
  /diagnosis|clinical|prescription|patient_record|medical_history|stripe|payment_intent|client_secret|password|token|provider_secret|file_contents|document|study|recipe|api_payload/i;
const PAYLOAD_KEY_SET = new Set<string>(SESSION_PAYLOAD_KEYS);

function fail(field: string, rule: string): never {
  throw new SessionContractError('MXMED_SESSION_CONTRACT_INVALID', field, rule);
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function buildSessionPrefix(environmentCode: SessionEnvironmentCode): string {
  return `mxmed:${environmentCode}:session:`;
}

function hasControlCharacter(value: string): boolean {
  for (let index = 0; index < value.length; index += 1) {
    const codeUnit = value.charCodeAt(index);
    if (codeUnit <= 31 || codeUnit === 127) return true;
  }
  return false;
}

export function validateOpaqueSessionId(opaqueSessionId: string): void {
  if (
    !OPAQUE_SESSION_ID_PATTERN.test(opaqueSessionId) ||
    IDENTIFYING_ID_PATTERN.test(opaqueSessionId) ||
    opaqueSessionId.includes('..') ||
    opaqueSessionId.includes('/') ||
    opaqueSessionId.includes('@') ||
    hasControlCharacter(opaqueSessionId)
  ) {
    fail('opaqueSessionId', 'must be a bounded opaque base64url-style identifier');
  }
}

export function buildSessionKey(
  environmentCode: SessionEnvironmentCode,
  opaqueSessionId: string,
): string {
  validateOpaqueSessionId(opaqueSessionId);
  return `${buildSessionPrefix(environmentCode)}${opaqueSessionId}`;
}

export function validateSessionPayloadSize(input: unknown): void {
  let serialized: unknown;
  try {
    serialized = JSON.stringify(input);
  } catch {
    fail('payload', 'must be serializable');
  }
  if (typeof serialized !== 'string' || Buffer.byteLength(serialized, 'utf8') > 32 * 1024) {
    fail('payload', 'must not exceed 32 KiB serialized');
  }
}

export function validateSessionPayloadKeys(
  input: unknown,
): asserts input is Readonly<Partial<Record<SessionPayloadKey, unknown>>> {
  if (!isRecord(input)) fail('payload', 'must be an object');
  for (const key of Object.keys(input)) {
    if (FORBIDDEN_PAYLOAD_KEY_PATTERN.test(key) || !PAYLOAD_KEY_SET.has(key)) {
      fail('payload', 'contains a forbidden key');
    }
  }
  validateSessionPayloadSize(input);
}

export function validateSessionCookieContract(
  input: unknown,
): asserts input is SessionCookieContract {
  if (!isRecord(input)) fail('cookie', 'must be an object');
  const expected = SESSION_COOKIE_CONTRACT as unknown as Readonly<Record<string, unknown>>;
  if (
    Object.keys(input).length !== Object.keys(expected).length ||
    Object.entries(expected).some(([key, value]) => input[key] !== value)
  ) {
    fail('cookie', 'must match the __Host cookie contract exactly');
  }
}

export function validateSessionAcl(input: unknown): asserts input is SessionAclContract {
  if (!isRecord(input)) fail('acl', 'must be an object');
  const environmentCode = input.environmentCode;
  if (environmentCode !== 'stg' && environmentCode !== 'prd') {
    fail('environmentCode', 'must be stg or prd');
  }
  if (
    input.keyPattern !== `~${buildSessionPrefix(environmentCode)}*` ||
    input.keyPattern === '~*'
  ) {
    fail('keyPattern', 'must be restricted to the environment session prefix');
  }
  if (
    !Array.isArray(input.allowedCommands) ||
    input.allowedCommands.length !== SESSION_ACL_COMMANDS.length ||
    input.allowedCommands.some((command, index) => command !== SESSION_ACL_COMMANDS[index])
  ) {
    fail('allowedCommands', 'must match the PP260 minimum allowlist exactly');
  }
}

export function validateSessionExpiration(
  input: unknown,
): asserts input is SessionExpirationContract {
  if (!isRecord(input)) fail('expiration', 'must be an object');
  const createdAt = input.createdAtEpochSeconds;
  const lastSeenAt = input.lastSeenAtEpochSeconds;
  const absoluteExpiresAt = input.absoluteExpiresAtEpochSeconds;
  if (
    input.idleTtlSeconds !== 1800 ||
    input.absoluteLifetimeSeconds !== 43200 ||
    !Number.isInteger(createdAt) ||
    !Number.isInteger(lastSeenAt) ||
    !Number.isInteger(absoluteExpiresAt) ||
    Number(createdAt) <= 0 ||
    Number(lastSeenAt) < Number(createdAt) ||
    Number(absoluteExpiresAt) !== Number(createdAt) + 43200 ||
    Number(lastSeenAt) > Number(absoluteExpiresAt)
  ) {
    fail('expiration', 'must enforce idle 1800 and absolute 43200 without extension');
  }
}

export function validateSessionLockContract(input: unknown): asserts input is SessionLockContract {
  if (!isRecord(input)) fail('lock', 'must be an object');
  const expected = SESSION_LOCK_CONTRACT as unknown as Readonly<Record<string, unknown>>;
  if (
    Object.keys(input).length !== Object.keys(expected).length ||
    Object.entries(expected).some(([key, value]) => input[key] !== value)
  ) {
    fail('lock', 'must be enabled with bounded timeout, wait and retries');
  }
}

export function buildSessionAclContract(
  environmentCode: SessionEnvironmentCode,
): SessionAclContract {
  const contract: SessionAclContract = Object.freeze({
    environmentCode,
    keyPattern: `~${buildSessionPrefix(environmentCode)}*`,
    allowedCommands: SESSION_ACL_COMMANDS,
  });
  validateSessionAcl(contract);
  return contract;
}

export function buildSessionApplicationAccessString(
  environmentCode: SessionEnvironmentCode,
): string {
  const contract = buildSessionAclContract(environmentCode);
  return [
    'on',
    contract.keyPattern,
    ...contract.allowedCommands.map((command) => `+${command}`),
  ].join(' ');
}
