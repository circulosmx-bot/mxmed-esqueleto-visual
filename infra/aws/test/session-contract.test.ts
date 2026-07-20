import {
  SESSION_COOKIE_CONTRACT,
  SESSION_LOCK_CONTRACT,
  buildSessionKey,
  buildSessionPrefix,
  validateOpaqueSessionId,
  validateSessionCookieContract,
  validateSessionExpiration,
  validateSessionLockContract,
  validateSessionPayloadKeys,
  validateSessionPayloadSize,
} from '../lib/constructs/session-contract';

const OPAQUE_ID = 'A7b9_C2d4-E6f8_G1h3-J5k7_L9m2-N4p6';

describe('session pure contracts', () => {
  test('SESSION-IMP-098 builds the staging prefix', () => {
    expect(buildSessionPrefix('stg')).toBe('mxmed:stg:session:');
  });
  test('SESSION-IMP-099 builds the production prefix', () => {
    expect(buildSessionPrefix('prd')).toBe('mxmed:prd:session:');
  });
  test('SESSION-IMP-100 builds a valid opaque session key', () => {
    expect(buildSessionKey('stg', OPAQUE_ID)).toBe(`mxmed:stg:session:${OPAQUE_ID}`);
  });
  test('SESSION-IMP-101 rejects a short or malformed session ID', () => {
    expect(() => {
      validateOpaqueSessionId('short');
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID');
    expect(() => {
      validateOpaqueSessionId(`${OPAQUE_ID}/../x`);
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID');
  });
  test('SESSION-IMP-102 rejects an identifying session ID', () => {
    expect(() => {
      validateOpaqueSessionId(`doctor_${'A1'.repeat(20)}`);
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID');
  });
  test('SESSION-IMP-103 accepts an allowlisted minimal payload', () => {
    expect(() => {
      validateSessionPayloadKeys({
        subject_id: 'opaque-subject',
        entity_type: 'doctor',
        authenticated: true,
        created_at: 1,
        absolute_expires_at: 43201,
      });
    }).not.toThrow();
  });
  test('SESSION-IMP-104 rejects a payload larger than 32 KiB', () => {
    expect(() => {
      validateSessionPayloadSize({ csrf_state: 'x'.repeat(33 * 1024) });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:payload');
  });
  test('SESSION-IMP-105 rejects a clinical key', () => {
    expect(() => {
      validateSessionPayloadKeys({ diagnosis: 'forbidden' });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:payload');
  });
  test('SESSION-IMP-106 rejects Stripe or client secret keys', () => {
    expect(() => {
      validateSessionPayloadKeys({ client_secret: 'forbidden' });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:payload');
  });
  test('SESSION-IMP-107 accepts the exact __Host cookie contract', () => {
    expect(() => {
      validateSessionCookieContract(SESSION_COOKIE_CONTRACT);
    }).not.toThrow();
  });
  test('SESSION-IMP-108 rejects a cookie Domain', () => {
    expect(() => {
      validateSessionCookieContract({ ...SESSION_COOKIE_CONTRACT, domain: 'invalid' });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:cookie');
  });
  test('SESSION-IMP-109 rejects Secure false', () => {
    expect(() => {
      validateSessionCookieContract({ ...SESSION_COOKIE_CONTRACT, secure: false });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:cookie');
  });
  test('SESSION-IMP-110 rejects SameSite None', () => {
    expect(() => {
      validateSessionCookieContract({ ...SESSION_COOKIE_CONTRACT, sameSite: 'None' });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:cookie');
  });
  test('SESSION-IMP-111 accepts the bounded lock contract', () => {
    expect(() => {
      validateSessionLockContract(SESSION_LOCK_CONTRACT);
    }).not.toThrow();
  });
  test('SESSION-IMP-112 rejects an unbounded lock', () => {
    expect(() => {
      validateSessionLockContract({ ...SESSION_LOCK_CONTRACT, maximumWaitSeconds: Infinity });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:lock');
  });
  test('SESSION-IMP-113 accepts idle and absolute expiration', () => {
    expect(() => {
      validateSessionExpiration({
        idleTtlSeconds: 3600,
        absoluteLifetimeSeconds: 43200,
        createdAtEpochSeconds: 1000,
        lastSeenAtEpochSeconds: 2000,
        absoluteExpiresAtEpochSeconds: 44200,
      });
    }).not.toThrow();
  });
  test('SESSION-IMP-114 rejects infinite or non-contractual TTL', () => {
    expect(() => {
      validateSessionExpiration({
        idleTtlSeconds: 0,
        absoluteLifetimeSeconds: 43200,
        createdAtEpochSeconds: 1000,
        lastSeenAtEpochSeconds: 2000,
        absoluteExpiresAtEpochSeconds: 44200,
      });
    }).toThrow('MXMED_SESSION_CONTRACT_INVALID:expiration');
  });
});
