import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEmailStage } from '../lib/stages/mxmed-email-stage';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function renderTemplates(config: MxMedEnvironmentConfig): Record<string, unknown> {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
  const environment = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const email = new MxMedEmailStage(app, `MxMed${suffix}Email`, { config });
  const stacks = [
    ...environment.node.children.filter((child): child is Stack => Stack.isStack(child)),
    email.emailStack,
  ];

  return Object.fromEntries(
    stacks.map((stack) => [stack.stackName, Template.fromStack(stack).toJSON()]),
  );
}

function readTypeScriptFiles(root: string): string {
  return readdirSync(root)
    .sort()
    .map((entry) => {
      const path = join(root, entry);
      return statSync(path).isDirectory() ? readTypeScriptFiles(path) : readFileSync(path, 'utf8');
    })
    .join('\n');
}

describe.each([
  ['staging', STAGING_CONFIG],
  ['production', PRODUCTION_CONFIG],
] as const)('%s offline synthesis', (_name, config) => {
  test('does not require an account and creates resources only in Network and Security', () => {
    const previousAccount = process.env.CDK_DEFAULT_ACCOUNT;
    const previousAccessKey = process.env.AWS_ACCESS_KEY_ID;
    const previousSecretKey = process.env.AWS_SECRET_ACCESS_KEY;
    delete process.env.CDK_DEFAULT_ACCOUNT;
    delete process.env.AWS_ACCESS_KEY_ID;
    delete process.env.AWS_SECRET_ACCESS_KEY;

    try {
      const templates = renderTemplates(config);
      expect(Object.keys(templates)).toHaveLength(11);
      for (const [stackName, template] of Object.entries(templates)) {
        const rendered = template as { Resources?: Record<string, unknown> };
        const resources = Object.values(rendered.Resources ?? {}) as { Type?: string }[];
        if (stackName === `mxmed-${config.environmentCode}-network`) {
          expect(resources.length).toBeGreaterThan(0);
          expect(resources.some((resource) => resource.Type === 'AWS::EC2::VPC')).toBe(true);
        } else if (stackName === `mxmed-${config.environmentCode}-security`) {
          expect(resources.length).toBeGreaterThan(0);
          expect(resources.filter((resource) => resource.Type === 'AWS::KMS::Key')).toHaveLength(4);
          expect(resources.some((resource) => resource.Type === 'AWS::CloudTrail::Trail')).toBe(
            true,
          );
        } else {
          expect(resources).toHaveLength(0);
        }
        expect(resources.map((resource) => resource.Type)).not.toEqual(
          expect.arrayContaining([
            'AWS::ECS::Service',
            'AWS::RDS::DBInstance',
            'AWS::ElastiCache::ReplicationGroup',
            'AWS::ElasticLoadBalancingV2::LoadBalancer',
            'AWS::CloudFront::Distribution',
            'AWS::WAFv2::WebACL',
          ]),
        );
      }
      expect(JSON.stringify(templates)).not.toMatch(
        /AKIA|ASIA|arn:aws|sk_(?:live|test)|\b\d{12}\b|BEGIN PRIVATE KEY/,
      );
    } finally {
      if (previousAccount === undefined) delete process.env.CDK_DEFAULT_ACCOUNT;
      else process.env.CDK_DEFAULT_ACCOUNT = previousAccount;
      if (previousAccessKey === undefined) delete process.env.AWS_ACCESS_KEY_ID;
      else process.env.AWS_ACCESS_KEY_ID = previousAccessKey;
      if (previousSecretKey === undefined) delete process.env.AWS_SECRET_ACCESS_KEY;
      else process.env.AWS_SECRET_ACCESS_KEY = previousSecretKey;
    }
  });

  test('produces deterministic templates', () => {
    expect(renderTemplates(config)).toEqual(renderTemplates(config));
  });
});

test('foundation source contains no lookup, SDK or remote-asset mechanism', () => {
  const source = [
    readTypeScriptFiles(join(process.cwd(), 'bin')),
    readTypeScriptFiles(join(process.cwd(), 'lib')),
  ].join('\n');
  expect(source).not.toMatch(
    /Vpc\.fromLookup|HostedZone\.fromLookup|AwsCustomResource|aws-sdk|@aws-sdk|DockerImageAsset|BundlingOptions|ContextProvider/,
  );
});
