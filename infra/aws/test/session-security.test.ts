import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderSession, resourcesOfType } from './session-test-helpers';

const staging = renderSession(STAGING_CONFIG);
const production = renderSession(PRODUCTION_CONFIG);
const stagingSecret = firstResource(staging, 'AWS::SecretsManager::Secret');
const productionSecret = firstResource(production, 'AWS::SecretsManager::Secret');

describe('session authentication secret', () => {
  test('SESSION-IMP-038 creates the staging secret at the contracted path', () => {
    expect(properties(stagingSecret).Name).toBe('/mxmed/staging/application/session-store-auth');
  });
  test('SESSION-IMP-039 creates a separate production secret', () => {
    expect(properties(productionSecret).Name).toBe(
      '/mxmed/production/application/session-store-auth',
    );
    expect(properties(productionSecret).Name).not.toBe(properties(stagingSecret).Name);
  });
  test('SESSION-IMP-040 encrypts the secret with SecretsKey', () => {
    expect(JSON.stringify(properties(stagingSecret).KmsKeyId)).toContain('SecretsKey');
  });
  test('SESSION-IMP-041 generates rather than embeds the password', () => {
    expect(properties(stagingSecret)).toHaveProperty('GenerateSecretString');
    expect(properties(stagingSecret)).not.toHaveProperty('SecretString');
  });
  test('SESSION-IMP-042 generates 64 characters', () => {
    expect(
      (properties(stagingSecret).GenerateSecretString as Record<string, unknown>).PasswordLength,
    ).toBe(64);
  });
  test('SESSION-IMP-043 excludes comma quote slash at-sign and space', () => {
    const generator = properties(stagingSecret).GenerateSecretString as Record<string, unknown>;
    expect(generator.ExcludeCharacters).toBe(',"/@');
    expect(generator.IncludeSpace).toBe(false);
    expect(generator.RequireEachIncludedType).toBe(true);
  });
  test('SESSION-IMP-044 templates only the application username', () => {
    const generator = properties(stagingSecret).GenerateSecretString as Record<string, unknown>;
    expect(JSON.parse(String(generator.SecretStringTemplate))).toEqual({
      username: 'mxmed_session_app',
    });
  });
  test('SESSION-IMP-045 retains the secret on delete and replacement', () => {
    expect(stagingSecret.DeletionPolicy).toBe('Retain');
    expect(stagingSecret.UpdateReplacePolicy).toBe('Retain');
  });
  test('SESSION-IMP-046 contains no plaintext credential', () => {
    expect(JSON.stringify([stagingSecret, productionSecret])).not.toMatch(/"SecretString":/);
  });
  test('SESSION-IMP-047 exposes no CloudFormation output', () => {
    expect(staging.outputs).toEqual({});
    expect(production.outputs).toEqual({});
  });
  test('SESSION-IMP-048 creates no rotation Lambda or replica secret', () => {
    expect(resourcesOfType(staging.resources, 'AWS::Lambda::Function')).toHaveLength(0);
    expect(properties(stagingSecret)).not.toHaveProperty('ReplicaRegions');
  });
});
