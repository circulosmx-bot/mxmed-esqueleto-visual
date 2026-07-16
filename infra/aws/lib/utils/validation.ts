export type MxMedValidationCode =
  | 'MXMED_CONFIG_INVALID'
  | 'MXMED_CONFIG_SENSITIVE_FIELD'
  | 'MXMED_CONFIG_CREDENTIAL_LIKE_VALUE'
  | 'MXMED_ENVIRONMENT_INVALID'
  | 'MXMED_NAMING_INVALID';

export class MxMedValidationError extends Error {
  public constructor(
    public readonly code: MxMedValidationCode,
    public readonly field: string,
    public readonly rule: string,
  ) {
    super(`${code}:${field}:${rule}`);
    this.name = 'MxMedValidationError';
  }
}

export function assertMxMedCondition(
  condition: boolean,
  code: MxMedValidationCode,
  field: string,
  rule: string,
): asserts condition {
  if (!condition) {
    throw new MxMedValidationError(code, field, rule);
  }
}

const SENSITIVE_FIELD_PATTERN =
  /secret|password|token|credential|private.?key|access.?key|account.?id|bucket.?name|patient|doctor|filename|curp|diagnosis|(^|[^a-z])arn([^a-z]|$)/i;

const CONTRACTED_NON_SENSITIVE_FIELDS = new Set(['secretRecoveryWindowDays']);

const CREDENTIAL_VALUE_PATTERNS = [
  /\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/,
  /\barn:aws[a-z-]*:/i,
  /\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{12,}\b/,
  /\bpi_[A-Za-z0-9]+_secret_[A-Za-z0-9]+\b/,
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /^\d{12}$/,
  /:\/\/[^:\s]+:[^@\s]+@/,
];

export function assertNoSensitiveConfiguration(input: unknown): void {
  const visited = new WeakSet();

  const inspect = (value: unknown): void => {
    if (typeof value === 'string') {
      const credentialLike = CREDENTIAL_VALUE_PATTERNS.some((pattern) => pattern.test(value));
      assertMxMedCondition(
        !credentialLike,
        'MXMED_CONFIG_CREDENTIAL_LIKE_VALUE',
        'configuration',
        'credential-like values are forbidden',
      );
      return;
    }

    if (typeof value !== 'object' || value === null || visited.has(value)) {
      return;
    }

    visited.add(value);

    for (const [field, child] of Object.entries(value)) {
      assertMxMedCondition(
        CONTRACTED_NON_SENSITIVE_FIELDS.has(field) || !SENSITIVE_FIELD_PATTERN.test(field),
        'MXMED_CONFIG_SENSITIVE_FIELD',
        'configuration',
        'sensitive field names are forbidden',
      );
      inspect(child);
    }
  };

  inspect(input);
}
