import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import {
  firstResource,
  properties,
  renderSession,
  resourcesOfType,
  userByName,
} from './session-test-helpers';

const staging = renderSession(STAGING_CONFIG);
const production = renderSession(PRODUCTION_CONFIG);
const stagingApplication = userByName(staging, 'mxmed_session_app');
const productionApplication = userByName(production, 'mxmed_session_app');
const stagingDefault = userByName(staging, 'default');
const appAccess = String(properties(stagingApplication).AccessString);

function authenticationText(resource: ReturnType<typeof userByName>): string {
  return JSON.stringify(properties(resource).AuthenticationMode);
}

describe('session Valkey RBAC', () => {
  test('SESSION-IMP-049 creates the application user', () => {
    expect(properties(stagingApplication)).toMatchObject({
      UserId: 'mxmed-stg-session-app',
      UserName: 'mxmed_session_app',
    });
  });
  test('SESSION-IMP-050 creates the compatibility default user', () => {
    expect(properties(stagingDefault)).toMatchObject({
      UserId: 'mxmed-stg-default-disabled',
      UserName: 'default',
    });
  });
  test('SESSION-IMP-051 uses Valkey for both users', () => {
    for (const [, user] of resourcesOfType(staging.resources, 'AWS::ElastiCache::User')) {
      expect(properties(user).Engine).toBe('valkey');
    }
  });
  test('SESSION-IMP-052 creates one Valkey user group', () => {
    const groups = resourcesOfType(staging.resources, 'AWS::ElastiCache::UserGroup');
    expect(groups).toHaveLength(1);
    expect(properties(firstResource(staging, 'AWS::ElastiCache::UserGroup')).Engine).toBe('valkey');
  });
  test('SESSION-IMP-053 puts exactly two users in the group', () => {
    const group = firstResource(staging, 'AWS::ElastiCache::UserGroup');
    expect(properties(group).UserIds).toHaveLength(2);
  });
  test('SESSION-IMP-054 gives the application user a versionless dynamic password', () => {
    expect(authenticationText(stagingApplication)).toContain('resolve:secretsmanager');
    expect(authenticationText(stagingApplication)).toContain('SecretString:password::}}');
  });
  test('SESSION-IMP-055 gives default the same dynamic password reference', () => {
    expect(properties(stagingDefault).AuthenticationMode).toEqual(
      properties(stagingApplication).AuthenticationMode,
    );
  });
  test('SESSION-IMP-056 turns the default user off', () => {
    expect(properties(stagingDefault).AccessString).toBe('off ~* -@all');
  });
  test('SESSION-IMP-057 removes all commands from default', () => {
    const access = String(properties(stagingDefault).AccessString);
    expect(access).toContain('-@all');
    expect(access).not.toMatch(/\bon\b|\+[a-z@]/i);
  });
  test('SESSION-IMP-058 scopes staging keys exactly', () => {
    expect(appAccess).toContain('~mxmed:stg:session:*');
  });
  test('SESSION-IMP-059 scopes production keys separately', () => {
    expect(String(properties(productionApplication).AccessString)).toContain(
      '~mxmed:prd:session:*',
    );
  });
  test('SESSION-IMP-060 allows GET', () => {
    expect(appAccess).toContain('+get');
  });
  test('SESSION-IMP-061 allows SET', () => {
    expect(appAccess).toContain('+set');
  });
  test('SESSION-IMP-062 allows SETEX', () => {
    expect(appAccess).toContain('+setex');
  });
  test('SESSION-IMP-063 allows DEL and UNLINK', () => {
    expect(appAccess).toContain('+del');
    expect(appAccess).toContain('+unlink');
  });
  test('SESSION-IMP-064 allows expiry and TTL commands', () => {
    for (const command of ['+expire', '+pexpire', '+ttl', '+pttl', '+touch']) {
      expect(appAccess).toContain(command);
    }
  });
  test('SESSION-IMP-065 allows PING', () => {
    expect(appAccess).toContain('+ping');
  });
  test('SESSION-IMP-066 grants no command category', () => {
    expect(appAccess).not.toContain('+@all');
    expect(appAccess).not.toContain('+@read');
    expect(appAccess).not.toContain('+@write');
  });
  test('SESSION-IMP-067 grants no KEYS', () => {
    expect(appAccess).not.toMatch(/\+keys\b/i);
  });
  test('SESSION-IMP-068 grants no SCAN', () => {
    expect(appAccess).not.toMatch(/\+scan\b/i);
  });
  test('SESSION-IMP-069 grants no FLUSH command', () => {
    expect(appAccess).not.toMatch(/flush/i);
  });
  test('SESSION-IMP-070 grants no CONFIG', () => {
    expect(appAccess).not.toMatch(/\+config\b/i);
  });
  test('SESSION-IMP-071 grants no ACL administration', () => {
    expect(appAccess).not.toMatch(/\+acl\b/i);
  });
  test('SESSION-IMP-072 grants no scripting or EVAL', () => {
    expect(appAccess).not.toMatch(/eval|script/i);
  });
  test('SESSION-IMP-073 gives the application no global key wildcard', () => {
    expect(appAccess.split(' ')).not.toContain('~*');
  });
});
