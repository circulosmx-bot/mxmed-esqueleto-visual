import { CfnOutput, CfnParameter, CfnRule, Fn, RemovalPolicy, Stack, Tags } from 'aws-cdk-lib';
import { CfnBudget } from 'aws-cdk-lib/aws-budgets';
import { CfnAnomalyMonitor, CfnAnomalySubscription } from 'aws-cdk-lib/aws-ce';
import { Effect, PolicyStatement, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import { CfnAlias, CfnKey, Key } from 'aws-cdk-lib/aws-kms';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnTopic, Topic } from 'aws-cdk-lib/aws-sns';
import type { Construct } from 'constructs';

import { operationsCreatesCost } from '../config/operations-profiles';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

const COST_SCOPE_FILTER = Object.freeze({
  production: 'user:CostScope$mxmed-production',
  staging: 'user:CostScope$mxmed-staging',
} as const);

const BUDGET_THRESHOLDS = Object.freeze([
  { threshold: 50, notificationType: 'ACTUAL' },
  { threshold: 75, notificationType: 'ACTUAL' },
  { threshold: 90, notificationType: 'FORECASTED' },
  { threshold: 100, notificationType: 'ACTUAL' },
  { threshold: 120, notificationType: 'ACTUAL' },
] as const);

export class MxMedCostManagementStack extends BaseMxMedStack {
  private readonly config: MxMedContractStackProps['config'];
  public readonly approvedMonthlyBudgetUsdParameter: CfnParameter;
  public readonly stagingMonthlyBudgetUsdParameter: CfnParameter;
  public readonly anomalyAlertThresholdUsdParameter: CfnParameter;
  public readonly maxInfrastructureCostToRevenuePercentParameter: CfnParameter;
  public readonly budgetOwnerReferenceParameter: CfnParameter;
  public readonly costReviewCadenceParameter: CfnParameter;
  public readonly costAllocationTagsVerifiedParameter: CfnParameter;
  public readonly externalSubscribersVerifiedParameter: CfnParameter;
  public readonly existingServiceAnomalyMonitorArnParameter?: CfnParameter;
  public readonly globalNotificationsKeyResource: CfnKey;
  public readonly globalNotificationsKey: IKey;
  public readonly costAlertsTopic: Topic;
  public readonly productionBudget: CfnBudget;
  public readonly stagingBudget: CfnBudget;
  public readonly serviceAnomalyMonitor?: CfnAnomalyMonitor;
  public readonly tagAnomalyMonitor?: CfnAnomalyMonitor;
  public readonly anomalySubscription: CfnAnomalySubscription;
  public readonly anomalyMonitorArn: string;
  public readonly costControlState = 'parameters-required-no-defaults-v1' as const;

  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'cost-management',
      description: 'MXMed account-scoped cost controls; deployment remains external.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'required' },
    });
    if (!operationsCreatesCost(props.config)) {
      throw new Error('MXMED_COST_MANAGEMENT_DISABLED');
    }
    if (this.region !== 'us-east-1') throw new Error('MXMED_COST_MANAGEMENT_REGION_INVALID');
    this.config = props.config;

    this.approvedMonthlyBudgetUsdParameter = this.numberParameter(
      'ApprovedMonthlyBudgetUsd',
      1,
      undefined,
      'Approved monthly production budget in USD; no default.',
    );
    this.stagingMonthlyBudgetUsdParameter = this.numberParameter(
      'StagingMonthlyBudgetUsd',
      1,
      undefined,
      'Approved monthly staging budget in USD; no default.',
    );
    this.anomalyAlertThresholdUsdParameter = this.numberParameter(
      'AnomalyAlertThresholdUsd',
      1,
      undefined,
      'Absolute anomaly impact threshold in USD; no default.',
    );
    this.maxInfrastructureCostToRevenuePercentParameter = this.numberParameter(
      'MaxInfrastructureCostToRevenuePercent',
      0,
      1000,
      'Approved infrastructure cost-to-revenue percentage; no default.',
    );
    this.budgetOwnerReferenceParameter = new CfnParameter(this, 'BudgetOwnerReference', {
      type: 'String',
      minLength: 3,
      maxLength: 128,
      allowedPattern: '^[A-Za-z0-9][A-Za-z0-9._/-]{2,127}$',
      constraintDescription: 'Use an opaque internal reference without email or phone.',
    });
    this.costReviewCadenceParameter = new CfnParameter(this, 'CostReviewCadence', {
      type: 'String',
      allowedValues: ['daily-first-30-days-v1', 'weekly-after-first-month-v1'],
    });
    this.costAllocationTagsVerifiedParameter = this.booleanStringParameter(
      'CostAllocationTagsVerified',
    );
    this.externalSubscribersVerifiedParameter = this.booleanStringParameter(
      'ExternalSubscribersVerified',
    );

    new CfnRule(this, 'CostAllocationTagsDeploymentGate', {
      assertions: [
        {
          assert: Fn.conditionEquals(
            this.costAllocationTagsVerifiedParameter.valueAsString,
            'true',
          ),
          assertDescription:
            'CostScope must be manually activated and verified before Cost Management deployment.',
        },
      ],
    });
    if (props.config.operationsNotificationMode === 'external-subscribers-confirmed-v1') {
      new CfnRule(this, 'ExternalSubscribersDeploymentGate', {
        assertions: [
          {
            assert: Fn.conditionEquals(
              this.externalSubscribersVerifiedParameter.valueAsString,
              'true',
            ),
            assertDescription:
              'External subscribers require confirmed private evidence before deployment.',
          },
        ],
      });
    }

    this.globalNotificationsKeyResource = this.createGlobalNotificationsKey();
    this.globalNotificationsKey = Key.fromKeyArn(
      this,
      'GlobalNotificationsKeyReference',
      this.globalNotificationsKeyResource.attrArn,
    );
    this.costAlertsTopic = new Topic(this, 'CostAlertsTopic', {
      topicName: 'mxmed-cost-alerts',
      displayName: 'MXMed cost alerts',
      masterKey: this.globalNotificationsKey,
    });
    this.retainTopic(this.costAlertsTopic);
    this.addCostTopicPolicies();

    this.productionBudget = this.createBudget(
      'ProductionMonthlyCostBudget',
      'mxmed-production-monthly-cost',
      this.approvedMonthlyBudgetUsdParameter,
      COST_SCOPE_FILTER.production,
    );
    this.stagingBudget = this.createBudget(
      'StagingMonthlyCostBudget',
      'mxmed-staging-monthly-cost',
      this.stagingMonthlyBudgetUsdParameter,
      COST_SCOPE_FILTER.staging,
    );

    if (props.config.costAnomalyMonitorOwnershipMode === 'create-service-monitor-v1') {
      this.serviceAnomalyMonitor = new CfnAnomalyMonitor(this, 'ServiceAnomalyMonitor', {
        monitorName: 'mxmed-service-cost-monitor',
        monitorType: 'DIMENSIONAL',
        monitorDimension: 'SERVICE',
        resourceTags: this.costResourceTags(),
      });
      this.anomalyMonitorArn = this.serviceAnomalyMonitor.attrMonitorArn;
    } else {
      this.existingServiceAnomalyMonitorArnParameter = new CfnParameter(
        this,
        'ExistingServiceAnomalyMonitorArn',
        {
          type: 'String',
          allowedPattern: '^arn:[a-z0-9-]+:ce::[0-9]{12}:anomalymonitor/[A-Za-z0-9._/-]+$',
          constraintDescription: 'Provide the approved existing service anomaly monitor ARN.',
        },
      );
      this.anomalyMonitorArn = this.existingServiceAnomalyMonitorArnParameter.valueAsString;
    }

    const monitorArnList = [this.anomalyMonitorArn];
    if (props.config.operationsCostTagAnomalyMonitorMode === 'enabled-v1') {
      this.tagAnomalyMonitor = new CfnAnomalyMonitor(this, 'CostScopeTagAnomalyMonitor', {
        monitorName: 'mxmed-cost-scope-tag-monitor',
        monitorType: 'CUSTOM',
        monitorSpecification: JSON.stringify({
          Tags: {
            Key: 'CostScope',
            MatchOptions: ['EQUALS'],
            Values: ['mxmed-production', 'mxmed-staging'],
          },
        }),
        resourceTags: this.costResourceTags(),
      });
      monitorArnList.push(this.tagAnomalyMonitor.attrMonitorArn);
    }

    this.anomalySubscription = new CfnAnomalySubscription(this, 'CostAnomalySubscription', {
      subscriptionName: 'mxmed-cost-anomaly-alerts',
      frequency: 'IMMEDIATE',
      monitorArnList,
      subscribers: [{ type: 'SNS', address: this.costAlertsTopic.topicArn }],
      thresholdExpression: Fn.sub(
        '{"Dimensions":{"Key":"ANOMALY_TOTAL_IMPACT_ABSOLUTE","MatchOptions":["GREATER_THAN_OR_EQUAL"],"Values":["${Threshold}"]}}',
        { Threshold: this.anomalyAlertThresholdUsdParameter.valueAsString },
      ),
      resourceTags: this.costResourceTags(),
    });

    this.createSafeOutputs();
  }

  private numberParameter(
    id: string,
    minValue: number,
    maxValue: number | undefined,
    description: string,
  ): CfnParameter {
    return new CfnParameter(this, id, {
      type: 'Number',
      minValue,
      ...(maxValue === undefined ? {} : { maxValue }),
      description,
    });
  }

  private booleanStringParameter(id: string): CfnParameter {
    return new CfnParameter(this, id, {
      type: 'String',
      allowedValues: ['false', 'true'],
    });
  }

  private createGlobalNotificationsKey(): CfnKey {
    const sourceAccount = Stack.of(this).account;
    const viaSns = 'sns.us-east-1.amazonaws.com';
    const key = new CfnKey(this, 'GlobalOperationsNotificationsKey', {
      description: 'MXMed global Operations notifications key; no key material is exported.',
      enabled: true,
      enableKeyRotation: true,
      keySpec: 'SYMMETRIC_DEFAULT',
      keyUsage: 'ENCRYPT_DECRYPT',
      multiRegion: false,
      pendingWindowInDays: 30,
      keyPolicy: {
        Version: '2012-10-17',
        Statement: [
          this.keyUsageStatement('SnsServiceUsage', 'sns.amazonaws.com', sourceAccount, viaSns),
          this.keyUsageStatement(
            'BudgetsServiceUsage',
            'budgets.amazonaws.com',
            sourceAccount,
            viaSns,
            Fn.sub('arn:${AWS::Partition}:budgets::${AWS::AccountId}:*'),
          ),
          this.keyUsageStatement(
            'CostAlertsServiceUsage',
            'costalerts.amazonaws.com',
            sourceAccount,
            viaSns,
            Fn.sub('arn:${AWS::Partition}:ce::${AWS::AccountId}:anomalysubscription/*'),
          ),
          this.keyUsageStatement(
            'CloudWatchServiceUsage',
            'cloudwatch.amazonaws.com',
            sourceAccount,
            viaSns,
            Fn.sub('arn:${AWS::Partition}:cloudwatch:*:${AWS::AccountId}:alarm:*'),
          ),
        ],
      },
    });
    key.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    const alias = new CfnAlias(this, 'GlobalOperationsNotificationsKeyAlias', {
      aliasName: 'alias/mxmed-global-operations-notifications',
      targetKeyId: key.ref,
    });
    alias.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    return key;
  }

  private keyUsageStatement(
    sid: string,
    service: string,
    sourceAccount: string,
    viaService: string,
    sourceArn?: string,
  ): Record<string, unknown> {
    return {
      Sid: sid,
      Effect: 'Allow',
      Principal: { Service: service },
      Action: ['kms:GenerateDataKey*', 'kms:Decrypt'],
      Resource: '*',
      Condition: {
        StringEquals: {
          'aws:SourceAccount': sourceAccount,
          'kms:ViaService': viaService,
        },
        ...(sourceArn === undefined ? {} : { ArnLike: { 'aws:SourceArn': sourceArn } }),
      },
    };
  }

  private retainTopic(topic: Topic): void {
    const resource = topic.node.defaultChild;
    if (!(resource instanceof CfnTopic)) throw new Error('MXMED_COST_TOPIC_RESOURCE_INVALID');
    resource.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    Tags.of(topic).add('CostTier', 'fixed-critical', { priority: 500 });
  }

  private addCostTopicPolicies(): void {
    const sourceAccount = Stack.of(this).account;
    this.costAlertsTopic.addToResourcePolicy(
      new PolicyStatement({
        sid: 'BudgetsPublish',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('budgets.amazonaws.com')],
        actions: ['sns:Publish'],
        resources: [this.costAlertsTopic.topicArn],
        conditions: {
          StringEquals: { 'aws:SourceAccount': sourceAccount },
          ArnLike: {
            'aws:SourceArn': Fn.sub('arn:${AWS::Partition}:budgets::${AWS::AccountId}:*'),
          },
        },
      }),
    );
    this.costAlertsTopic.addToResourcePolicy(
      new PolicyStatement({
        sid: 'CostAnomalyDetectionPublish',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('costalerts.amazonaws.com')],
        actions: ['sns:Publish'],
        resources: [this.costAlertsTopic.topicArn],
        conditions: {
          StringEquals: { 'aws:SourceAccount': sourceAccount },
          ArnLike: {
            'aws:SourceArn': Fn.sub(
              'arn:${AWS::Partition}:ce::${AWS::AccountId}:anomalysubscription/*',
            ),
          },
        },
      }),
    );
  }

  private createBudget(
    id: string,
    budgetName: string,
    amount: CfnParameter,
    costScope: string,
  ): CfnBudget {
    return new CfnBudget(this, id, {
      budget: {
        budgetName,
        budgetType: 'COST',
        timeUnit: 'MONTHLY',
        budgetLimit: { amount: amount.valueAsNumber, unit: 'USD' },
        costFilters: { TagKeyValue: [costScope] },
        costTypes: {
          includeTax: false,
          includeSupport: true,
          includeSubscription: true,
          includeUpfront: true,
          includeRecurring: true,
          includeOtherSubscription: true,
          includeDiscount: true,
          includeCredit: true,
          includeRefund: true,
          useBlended: false,
          useAmortized: false,
        },
      },
      notificationsWithSubscribers: BUDGET_THRESHOLDS.map((threshold) => ({
        notification: {
          comparisonOperator: 'GREATER_THAN',
          threshold: threshold.threshold,
          thresholdType: 'PERCENTAGE',
          notificationType: threshold.notificationType,
        },
        subscribers: [{ subscriptionType: 'SNS', address: this.costAlertsTopic.topicArn }],
      })),
      resourceTags: this.costResourceTags(),
    });
  }

  private costResourceTags(): { readonly key: string; readonly value: string }[] {
    return [
      { key: 'Project', value: 'mxmed' },
      { key: 'Environment', value: this.config.environmentName },
      { key: 'CostReview', value: 'required' },
    ];
  }

  private createSafeOutputs(): void {
    new CfnOutput(this, 'ProductionBudgetId', { value: this.productionBudget.ref });
    new CfnOutput(this, 'StagingBudgetId', { value: this.stagingBudget.ref });
    new CfnOutput(this, 'AnomalyMonitorArn', { value: this.anomalyMonitorArn });
    new CfnOutput(this, 'AnomalySubscriptionArn', {
      value: this.anomalySubscription.attrSubscriptionArn,
    });
    new CfnOutput(this, 'CostAlertsTopicArn', { value: this.costAlertsTopic.topicArn });
    new CfnOutput(this, 'CostControlState', { value: this.costControlState });
    new CfnOutput(this, 'GlobalNotificationsKeyArn', {
      value: this.globalNotificationsKeyResource.attrArn,
    });
  }
}
