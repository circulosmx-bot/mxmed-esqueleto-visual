import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import {
  findByLogicalId,
  properties,
  renderSecurity,
  resourcesOfType,
} from './security-test-helpers';

function secretsFor(config: typeof STAGING_CONFIG | typeof PRODUCTION_CONFIG) {
  return resourcesOfType(renderSecurity(config).resources, 'AWS::SecretsManager::Secret');
}

function externalSecrets(config: typeof STAGING_CONFIG | typeof PRODUCTION_CONFIG) {
  return secretsFor(config).filter(([, secret]) =>
    String(properties(secret).Name).includes('/providers/'),
  );
}

describe('security secret contract', () => {
  test('SEC-IMP-026 generates session-signing in Secrets Manager', () => {
    const [, secret] = findByLogicalId(
      renderSecurity(STAGING_CONFIG).resources,
      'SessionSigningSecret',
    );
    expect(properties(secret).GenerateSecretString).toBeDefined();
    expect(properties(secret).SecretString).toBeUndefined();
  });

  test('SEC-IMP-027 generates session-signing with a safe length', () => {
    const [, secret] = findByLogicalId(
      renderSecurity(PRODUCTION_CONFIG).resources,
      'SessionSigningSecret',
    );
    expect(properties(secret).GenerateSecretString).toEqual({ PasswordLength: 64 });
  });

  test('SEC-IMP-028 creates exactly three external containers', () => {
    expect(externalSecrets(STAGING_CONFIG)).toHaveLength(3);
    expect(externalSecrets(PRODUCTION_CONFIG)).toHaveLength(3);
  });

  test('SEC-IMP-029 leaves SecretString absent from every external container', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      expect(
        externalSecrets(config).every(
          ([, secret]) => properties(secret).SecretString === undefined,
        ),
      ).toBe(true);
    }
  });

  test('SEC-IMP-030 leaves GenerateSecretString absent from external containers', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      expect(
        externalSecrets(config).every(
          ([, secret]) => properties(secret).GenerateSecretString === undefined,
        ),
      ).toBe(true);
    }
  });

  test('SEC-IMP-031 encrypts every secret with SecretsKey', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      for (const [, secret] of secretsFor(config)) {
        expect(JSON.stringify(properties(secret).KmsKeyId)).toContain('SecretsKey');
      }
    }
  });

  test('SEC-IMP-032 retains every secret on delete and replacement', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      expect(
        secretsFor(config).every(
          ([, secret]) =>
            secret.DeletionPolicy === 'Retain' && secret.UpdateReplacePolicy === 'Retain',
        ),
      ).toBe(true);
    }
  });

  test('SEC-IMP-033 uses exact staging secret names', () => {
    expect(
      secretsFor(STAGING_CONFIG)
        .map(([, secret]) => properties(secret).Name)
        .sort(),
    ).toEqual(
      [
        '/mxmed/staging/application/session-signing',
        '/mxmed/staging/providers/stripe/secret-key',
        '/mxmed/staging/providers/stripe/webhook-secret',
        '/mxmed/staging/providers/ai/api-key',
      ].sort(),
    );
  });

  test('SEC-IMP-034 uses exact production secret names', () => {
    expect(
      secretsFor(PRODUCTION_CONFIG)
        .map(([, secret]) => properties(secret).Name)
        .sort(),
    ).toEqual(
      [
        '/mxmed/production/application/session-signing',
        '/mxmed/production/providers/stripe/secret-key',
        '/mxmed/production/providers/stripe/webhook-secret',
        '/mxmed/production/providers/ai/api-key',
      ].sort(),
    );
  });

  test('SEC-IMP-035 includes no sandbox or live provider value', () => {
    const templates = JSON.stringify([
      renderSecurity(STAGING_CONFIG).template,
      renderSecurity(PRODUCTION_CONFIG).template,
    ]);
    expect(templates).not.toMatch(/(?:sk|rk)_(?:test|live)_[A-Za-z0-9]+/);
    expect(templates).not.toMatch(/whsec_[A-Za-z0-9]+/);
  });

  test('SEC-IMP-036 includes no change-me placeholder', () => {
    const templates = JSON.stringify([
      renderSecurity(STAGING_CONFIG).template,
      renderSecurity(PRODUCTION_CONFIG).template,
    ]);
    expect(templates).not.toMatch(/change[_-]?me/i);
  });

  test('SEC-IMP-037 exposes no secret output', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const outputs = renderSecurity(config).outputs;
      expect(Object.keys(outputs).every((key) => key.startsWith('ExportsOutput'))).toBe(true);
      expect(JSON.stringify(outputs)).not.toMatch(/SecretString|MasterUserSecret|password/i);
    }
  });

  test('SEC-IMP-038 does not duplicate the future RDS master secret', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const secrets = secretsFor(config);
      expect(secrets).toHaveLength(4);
      expect(JSON.stringify(secrets)).not.toMatch(/rds|database|master.?password/i);
      expect(renderSecurity(config).resources).not.toHaveProperty('AWS::RDS::DBInstance');
    }
  });
});
