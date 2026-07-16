import { STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderData, resourcesOfType } from './data-test-helpers';

const rendered = (): ReturnType<typeof renderData> => renderData(STAGING_CONFIG);
const parameterProperties = (): Readonly<Record<string, unknown>> =>
  properties(firstResource(rendered().resources, 'AWS::RDS::DBParameterGroup'));

describe('data subnet and parameter groups', () => {
  test('DATA-IMP-021 creates one subnet group', () => {
    expect(resourcesOfType(rendered().resources, 'AWS::RDS::DBSubnetGroup')).toHaveLength(1);
  });
  test('DATA-IMP-022 uses two isolated-data subnets', () => {
    const props = properties(firstResource(rendered().resources, 'AWS::RDS::DBSubnetGroup'));
    expect(props.SubnetIds).toHaveLength(2);
    expect(JSON.stringify(props.SubnetIds)).toMatch(/isolateddata/i);
  });
  test('DATA-IMP-023 includes no public subnet in the subnet group', () => {
    const props = properties(firstResource(rendered().resources, 'AWS::RDS::DBSubnetGroup'));
    expect(JSON.stringify(props.SubnetIds)).not.toMatch(
      /publicingress|privateapp|privateendpoints/i,
    );
  });
  test('DATA-IMP-024 creates one mysql8.4 parameter group', () => {
    expect(resourcesOfType(rendered().resources, 'AWS::RDS::DBParameterGroup')).toHaveLength(1);
    expect(parameterProperties().Family).toBe('mysql8.4');
  });
  test('DATA-IMP-025 requires secure transport', () => {
    expect(
      (parameterProperties().Parameters as Record<string, string>).require_secure_transport,
    ).toBe('ON');
  });
  test('DATA-IMP-026 sets utf8mb4 server charset', () => {
    expect((parameterProperties().Parameters as Record<string, string>).character_set_server).toBe(
      'utf8mb4',
    );
  });
  test('DATA-IMP-027 uses the PP255 collation', () => {
    expect((parameterProperties().Parameters as Record<string, string>).collation_server).toBe(
      'utf8mb4_unicode_ci',
    );
  });
  test('DATA-IMP-028 uses UTC', () => {
    expect((parameterProperties().Parameters as Record<string, string>).time_zone).toBe('UTC');
  });
  test('DATA-IMP-029 enables slow query logging at one second', () => {
    const params = parameterProperties().Parameters as Record<string, string>;
    expect([params.slow_query_log, params.long_query_time, params.log_output]).toEqual([
      '1',
      '1',
      'FILE',
    ]);
  });
  test('DATA-IMP-030 disables general log', () => {
    expect((parameterProperties().Parameters as Record<string, string>).general_log).toBe('0');
  });
  test('DATA-IMP-031 disables the event scheduler', () => {
    expect((parameterProperties().Parameters as Record<string, string>).event_scheduler).toBe(
      'OFF',
    );
  });
  test('DATA-IMP-032 uses ROW binlog format', () => {
    expect((parameterProperties().Parameters as Record<string, string>).binlog_format).toBe('ROW');
  });
  test('DATA-IMP-033 keeps lower_case_table_names at zero', () => {
    const params = parameterProperties().Parameters as Record<string, string>;
    expect(params.lower_case_table_names).toBe('0');
    expect(Object.keys(params)).toHaveLength(11);
  });
});
