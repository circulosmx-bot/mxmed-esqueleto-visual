import {
  activeBackupConfig,
  backupTemplate,
  crossRegionBackupConfig,
  resourceEntries,
  resourceProperties,
  restoreBackupConfig,
  templateText,
} from './backup-dr-test-helpers';

const regional = backupTemplate(activeBackupConfig());
const crossRegion = backupTemplate(crossRegionBackupConfig());
const restore = backupTemplate(restoreBackupConfig('manual-quarterly-v1'));

describe('AWS Backup failure monitoring', () => {
  test.each([
    ['regional', regional, 1],
    ['cross-region', crossRegion, 2],
    ['restore', restore, 3],
  ] as const)('%s profile creates %d sanitized failure rules', (_name, template, count) => {
    expect(resourceEntries(template, 'AWS::Events::Rule')).toHaveLength(count);
  });

  test.each(['FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL'])('routes backup state %s', (state) => {
    const rules = JSON.stringify(resourceProperties(regional, 'AWS::Events::Rule'));
    expect(rules).toContain(state);
  });

  test('routes copy failures only for the cross-region fixture', () => {
    expect(templateText(regional)).not.toContain('Copy Job State Change');
    expect(templateText(crossRegion)).toContain('Copy Job State Change');
  });

  test('routes restore failures only for the restore fixture', () => {
    expect(templateText(regional)).not.toContain('Restore Job State Change');
    expect(templateText(restore)).toContain('Restore Job State Change');
  });

  test.each([regional, crossRegion, restore])(
    'uses aws.backup as the only source and exactly one target per rule',
    (template) => {
      for (const rule of resourceProperties(template, 'AWS::Events::Rule')) {
        expect(rule.EventPattern).toMatchObject({ source: ['aws.backup'] });
        expect(rule.Targets).toHaveLength(1);
      }
    },
  );

  test('targets the existing regional critical topic reference', () => {
    const rules = resourceProperties(regional, 'AWS::Events::Rule');
    expect(JSON.stringify(rules[0]?.Targets)).toContain('RegionalCriticalTopic');
  });

  test('creates no subscriber or personal contact', () => {
    const text = templateText(regional);
    expect(text).not.toMatch(/AWS::SNS::Subscription|@|phone|email/i);
  });

  test('does not log event payloads', () => {
    expect(templateText(regional)).not.toMatch(/AWS::Logs|InputTransformer|InputPath/);
  });
});
