import { assertMxMedCondition } from './validation';

const COMPONENT_INPUT_PATTERN = /^[A-Za-z0-9 _-]+$/;

export function mxmedName(environmentCode: string, component: string, maximumLength = 128): string {
  assertMxMedCondition(
    environmentCode === 'stg' || environmentCode === 'prd',
    'MXMED_NAMING_INVALID',
    'environmentCode',
    'must be stg or prd',
  );
  assertMxMedCondition(
    Number.isInteger(maximumLength) && maximumLength > 0,
    'MXMED_NAMING_INVALID',
    'maximumLength',
    'must be a positive integer',
  );

  const trimmedComponent = component.trim();
  assertMxMedCondition(
    trimmedComponent.length > 0,
    'MXMED_NAMING_INVALID',
    'component',
    'must not be empty',
  );
  assertMxMedCondition(
    COMPONENT_INPUT_PATTERN.test(trimmedComponent),
    'MXMED_NAMING_INVALID',
    'component',
    'contains unsupported characters',
  );

  const normalizedComponent = trimmedComponent
    .toLowerCase()
    .replace(/[ _-]+/g, '-')
    .replace(/^-+|-+$/g, '');
  assertMxMedCondition(
    normalizedComponent.length > 0,
    'MXMED_NAMING_INVALID',
    'component',
    'must contain an alphanumeric character',
  );

  const name = `mxmed-${environmentCode}-${normalizedComponent}`;
  assertMxMedCondition(
    name.length <= maximumLength,
    'MXMED_NAMING_INVALID',
    'component',
    'normalized name exceeds maximum length',
  );
  assertMxMedCondition(
    /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name),
    'MXMED_NAMING_INVALID',
    'component',
    'normalized name must use lowercase alphanumeric segments',
  );

  return name;
}

export function mxmedC3AuditBucketName(account: string, region: string): string {
  assertMxMedCondition(
    /^[0-9]{12}$/.test(account),
    'MXMED_NAMING_INVALID',
    'account',
    'must be a 12-digit AWS account identifier',
  );
  assertMxMedCondition(
    /^[a-z]{2}-[a-z]+-[0-9]+$/.test(region),
    'MXMED_NAMING_INVALID',
    'region',
    'must be a lowercase AWS region identifier',
  );
  return `${mxmedName('stg', 'audit')}-${account}-${region}`;
}
