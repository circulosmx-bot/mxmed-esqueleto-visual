import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { first, properties, renderSecurity, resourcesOfType } from './security-test-helpers';

describe('security KMS foundation', () => {
  test('SEC-IMP-014 creates four staging customer-managed keys', () => {
    const rendered = renderSecurity(STAGING_CONFIG);
    expect(resourcesOfType(rendered.resources, 'AWS::KMS::Key')).toHaveLength(4);
    expect(rendered.stage.securityStack.applicationDataKey).toBeDefined();
    expect(rendered.stage.securityStack.secretsKey).toBeDefined();
    expect(rendered.stage.securityStack.auditKey).toBeDefined();
    expect(rendered.stage.securityStack.backupKey).toBeDefined();
  });

  test('SEC-IMP-015 creates four production customer-managed keys', () => {
    const { resources } = renderSecurity(PRODUCTION_CONFIG);
    expect(resourcesOfType(resources, 'AWS::KMS::Key')).toHaveLength(4);
  });

  test('SEC-IMP-016 creates the four exact environment aliases', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const { resources } = renderSecurity(config);
      const aliases = resourcesOfType(resources, 'AWS::KMS::Alias')
        .map(([, resource]) => properties(resource).AliasName)
        .sort();
      expect(aliases).toEqual(
        [
          `alias/mxmed-${config.environmentCode}-application-data`,
          `alias/mxmed-${config.environmentCode}-secrets`,
          `alias/mxmed-${config.environmentCode}-audit`,
          `alias/mxmed-${config.environmentCode}-backup`,
        ].sort(),
      );
    }
  });

  test('SEC-IMP-017 enables rotation on every key', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const keys = resourcesOfType(renderSecurity(config).resources, 'AWS::KMS::Key');
      expect(keys.every(([, key]) => properties(key).EnableKeyRotation === true)).toBe(true);
    }
  });

  test('SEC-IMP-018 uses symmetric encrypt/decrypt keys', () => {
    const keys = resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key');
    expect(
      keys.every(
        ([, key]) =>
          properties(key).KeySpec === 'SYMMETRIC_DEFAULT' &&
          properties(key).KeyUsage === 'ENCRYPT_DECRYPT',
      ),
    ).toBe(true);
  });

  test('SEC-IMP-019 keeps every key single-region', () => {
    const keys = resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key');
    expect(keys.every(([, key]) => properties(key).MultiRegion === false)).toBe(true);
  });

  test('SEC-IMP-020 uses a seven-day staging pending window', () => {
    const keys = resourcesOfType(renderSecurity(STAGING_CONFIG).resources, 'AWS::KMS::Key');
    expect(keys.every(([, key]) => properties(key).PendingWindowInDays === 7)).toBe(true);
  });

  test('SEC-IMP-021 uses a thirty-day production pending window', () => {
    const keys = resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key');
    expect(keys.every(([, key]) => properties(key).PendingWindowInDays === 30)).toBe(true);
  });

  test('SEC-IMP-022 retains every key on delete and replacement', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const keys = resourcesOfType(renderSecurity(config).resources, 'AWS::KMS::Key');
      expect(
        keys.every(
          ([, key]) => key.DeletionPolicy === 'Retain' && key.UpdateReplacePolicy === 'Retain',
        ),
      ).toBe(true);
    }
  });

  test('SEC-IMP-023 has no public Allow principal in KMS policies', () => {
    const keys = resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key');
    for (const [, key] of keys) {
      const document = properties(key).KeyPolicy as { Statement?: Record<string, unknown>[] };
      const unsafe = (document.Statement ?? []).filter(
        (statement) =>
          statement.Effect === 'Allow' && JSON.stringify(statement.Principal ?? {}).includes('"*"'),
      );
      expect(unsafe).toEqual([]);
    }
  });

  test('SEC-IMP-024 publishes no KMS or security outputs', () => {
    expect(renderSecurity(STAGING_CONFIG).outputs).toEqual({});
    expect(renderSecurity(PRODUCTION_CONFIG).outputs).toEqual({});
  });

  test('SEC-IMP-025 contains no literal account identifier', () => {
    const template = renderSecurity(PRODUCTION_CONFIG).template;
    expect(JSON.stringify(template)).not.toMatch(/\b\d{12}\b/);
    const key = first(
      resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key'),
      'key',
    );
    expect(JSON.stringify(properties(key[1]).KeyPolicy)).toContain('AWS::AccountId');
  });
});
