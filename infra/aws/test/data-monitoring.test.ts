import { STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderData, resourcesOfType } from './data-test-helpers';

describe('RDS Enhanced Monitoring role', () => {
  test('DATA-IMP-073 trusts only monitoring.rds.amazonaws.com', () => {
    const roles = resourcesOfType(renderData(STAGING_CONFIG).resources, 'AWS::IAM::Role');
    expect(roles).toHaveLength(1);
    const props = properties(firstResource(renderData(STAGING_CONFIG).resources, 'AWS::IAM::Role'));
    const trust = props.AssumeRolePolicyDocument as { Statement?: Record<string, unknown>[] };
    expect(trust.Statement).toHaveLength(1);
    expect(JSON.stringify(trust)).toContain('monitoring.rds.amazonaws.com');
    expect(JSON.stringify(trust)).not.toContain('ecs-tasks.amazonaws.com');
  });
  test('DATA-IMP-074 attaches only the official Enhanced Monitoring policy', () => {
    const props = properties(firstResource(renderData(STAGING_CONFIG).resources, 'AWS::IAM::Role'));
    expect(props.ManagedPolicyArns).toHaveLength(1);
    expect(JSON.stringify(props.ManagedPolicyArns)).toContain('AmazonRDSEnhancedMonitoringRole');
    expect(props).not.toHaveProperty('Policies');
    expect(props).not.toHaveProperty('PermissionsBoundary');
  });
});
