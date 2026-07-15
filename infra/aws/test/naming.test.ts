import { mxmedName } from '../lib/utils/naming';

describe('mxmedName', () => {
  test('builds stable staging and production names', () => {
    expect(mxmedName('stg', 'network')).toBe('mxmed-stg-network');
    expect(mxmedName('prd', 'data')).toBe('mxmed-prd-data');
  });

  test('normalizes spaces, underscores, case and repeated separators', () => {
    expect(mxmedName('stg', ' Cloud__Front---Ingress ')).toBe('mxmed-stg-cloud-front-ingress');
  });

  test('rejects an invalid environment code', () => {
    expect(() => mxmedName('dev', 'network')).toThrow('MXMED_NAMING_INVALID:environmentCode');
  });

  test('rejects unsupported characters', () => {
    expect(() => mxmedName('stg', 'edge$public')).toThrow('MXMED_NAMING_INVALID:component');
  });

  test('rejects an empty component', () => {
    expect(() => mxmedName('prd', '   ')).toThrow('MXMED_NAMING_INVALID:component');
    expect(() => mxmedName('prd', '---')).toThrow('MXMED_NAMING_INVALID:component');
  });

  test('rejects a name longer than the configured maximum', () => {
    expect(() => mxmedName('stg', 'a-very-long-component', 20)).toThrow(
      'MXMED_NAMING_INVALID:component:normalized name exceeds maximum length',
    );
  });
});
