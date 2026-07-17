import { CfnDeletionPolicy, CfnParameter, CfnResource, Stack, Token } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnBudget, CfnBudgetsAction } from 'aws-cdk-lib/aws-budgets';
import { CfnAnomalyMonitor, CfnAnomalySubscription } from 'aws-cdk-lib/aws-ce';
import { CfnAlarm, CfnDashboard } from 'aws-cdk-lib/aws-cloudwatch';
import { CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import { CfnSubscription, CfnTopic, CfnTopicPolicy } from 'aws-cdk-lib/aws-sns';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

const OPERATIONS_PATH = /(?:^|\/)(?:RegionalOperations|GlobalOperations|CostManagement)(?:\/|$)/;
const MONETARY_PARAMETERS = new Set([
  'ApprovedMonthlyBudgetUsd',
  'StagingMonthlyBudgetUsd',
  'AnomalyAlertThresholdUsd',
  'MaxInfrastructureCostToRevenuePercent',
]);
const PERSONAL_DIMENSION =
  /(?:user|doctor|profile|patient|email|payment|session|route|filename|token|url)/i;
const FORBIDDEN_LOG_TEXT =
  /(?:request.?body|cookie|authorization|stripe-signature|client_secret|full.?query|logs:unmask)/i;

function reject(condition: boolean, code: string): void {
  if (condition) throw new Error(`MXMED_OPERATIONS_GUARDRAIL:${code}`);
}

function json(value: unknown): string {
  return JSON.stringify(value);
}

function resourceProperties(resource: CfnResource): unknown {
  return (resource as unknown as { readonly cfnProperties?: unknown }).cfnProperties ?? {};
}

export class OperationsFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    const operationsOwned = OPERATIONS_PATH.test(node.node.path);
    reject(
      operationsOwned &&
        node instanceof CfnResource &&
        this.config.operationsActivationMode === 'disabled-v1',
      'RESOURCES_IN_DISABLED',
    );
    reject(this.config.operationsAutomaticRemediationEnabled, 'AUTOMATIC_REMEDIATION');

    if (
      node instanceof CfnParameter &&
      (MONETARY_PARAMETERS.has(node.logicalId) || MONETARY_PARAMETERS.has(node.node.id))
    ) {
      reject(node.default !== undefined, 'MONETARY_DEFAULT');
    }
    if (node instanceof CfnBudgetsAction) reject(true, 'BUDGET_ACTION');
    if (node instanceof CfnBudget) this.inspectBudget(node);
    if (node instanceof CfnAnomalySubscription) this.inspectAnomalySubscription(node);
    if (node instanceof CfnAnomalyMonitor) this.inspectAnomalyMonitor(node);
    if (node instanceof CfnTopic) this.inspectTopic(node, operationsOwned);
    if (node instanceof CfnTopicPolicy) this.inspectTopicPolicy(node);
    if (node instanceof CfnSubscription && operationsOwned) reject(true, 'EXTERNAL_SUBSCRIPTION');
    if (node instanceof CfnKey && operationsOwned) this.inspectKey(node);
    if (node instanceof CfnAlarm && operationsOwned) this.inspectAlarm(node);
    if (node instanceof CfnDashboard && operationsOwned) this.inspectDashboard(node);
    if (node instanceof CfnLogGroup) this.inspectLogGroup(node);
    if (node instanceof CfnResource && operationsOwned) this.inspectForbiddenResource(node);
  }

  private inspectBudget(budget: CfnBudget): void {
    const budgetText = json(budget.budget);
    const notificationsText = json(budget.notificationsWithSubscribers);
    reject(!budgetText.includes('TagKeyValue'), 'BUDGET_COST_SCOPE_FILTER');
    reject(!budgetText.includes('CostScope'), 'BUDGET_COST_SCOPE_FILTER');
    reject(/EMAIL/i.test(notificationsText), 'BUDGET_EMAIL_SUBSCRIBER');
    const notifications = budget.notificationsWithSubscribers;
    reject(!Array.isArray(notifications) || notifications.length !== 5, 'BUDGET_THRESHOLD_COUNT');
    for (const threshold of [50, 75, 90, 100, 120]) {
      reject(
        !notificationsText.includes(`"threshold":${String(threshold)}`),
        'BUDGET_THRESHOLD_SET',
      );
    }
  }

  private inspectAnomalySubscription(subscription: CfnAnomalySubscription): void {
    reject(subscription.threshold !== undefined, 'ANOMALY_DEPRECATED_THRESHOLD');
    reject(subscription.frequency !== 'IMMEDIATE', 'ANOMALY_FREQUENCY');
    reject(subscription.thresholdExpression === undefined, 'ANOMALY_THRESHOLD_EXPRESSION');
    reject(/EMAIL/i.test(json(subscription.subscribers)), 'ANOMALY_EMAIL_SUBSCRIBER');
  }

  private inspectAnomalyMonitor(monitor: CfnAnomalyMonitor): void {
    reject(
      monitor.monitorType === 'CUSTOM' &&
        this.config.operationsCostAllocationTagState !== 'active-and-verified-v1',
      'TAG_MONITOR_BEFORE_TAGS_ACTIVE',
    );
    reject(
      monitor.monitorType === 'DIMENSIONAL' &&
        this.config.costAnomalyMonitorOwnershipMode !== 'create-service-monitor-v1',
      'ANOMALY_MONITOR_OWNERSHIP',
    );
  }

  private inspectTopic(topic: CfnTopic, operationsOwned: boolean): void {
    if (!operationsOwned) return;
    reject(topic.kmsMasterKeyId === undefined, 'UNENCRYPTED_TOPIC');
    reject(topic.subscription !== undefined, 'TOPIC_SUBSCRIPTION');
  }

  private inspectTopicPolicy(policy: CfnTopicPolicy): void {
    const policyText = json(policy.policyDocument);
    reject(/"Principal"\s*:\s*"\*"/.test(policyText), 'TOPIC_WILDCARD_PRINCIPAL');
    reject(
      /(?:mailto:|https?:\/\/|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+[0-9]{7,}|"Service"\s*:\s*"\*")/i.test(
        policyText,
      ),
      'TOPIC_PERSONAL_TARGET',
    );
  }

  private inspectKey(key: CfnKey): void {
    const policyText = json(key.keyPolicy);
    reject(/"Action"\s*:\s*"kms:\*"/.test(policyText), 'KMS_WILDCARD_ADMIN');
    reject(/ScheduleKeyDeletion|PutKeyPolicy|CreateGrant/.test(policyText), 'KMS_ADMINISTRATION');
    reject(key.enableKeyRotation !== true, 'KMS_ROTATION');
  }

  private inspectAlarm(alarm: CfnAlarm): void {
    const description = alarm.alarmDescription;
    reject(
      typeof description !== 'string' ||
        !description.includes('severity=') ||
        !description.includes('runbook=') ||
        !description.includes('code='),
      'ALARM_METADATA',
    );
    reject(alarm.period !== undefined && alarm.period < 60, 'HIGH_RESOLUTION_ALARM');
    if (Array.isArray(alarm.metrics)) {
      reject(
        alarm.metrics.some(
          (metric) =>
            !Token.isUnresolved(metric) &&
            'metricStat' in metric &&
            !Token.isUnresolved(metric.metricStat) &&
            (metric.metricStat as CfnAlarm.MetricStatProperty).period < 60,
        ),
        'HIGH_RESOLUTION_ALARM',
      );
    }
    const resolvedOkActions = Stack.of(alarm).resolve(alarm.okActions) as unknown;
    const resolvedInsufficientDataActions = Stack.of(alarm).resolve(
      alarm.insufficientDataActions,
    ) as unknown;
    const resolvedAlarmActions = Stack.of(alarm).resolve(alarm.alarmActions) as unknown;
    reject(
      !Array.isArray(resolvedAlarmActions) || resolvedAlarmActions.length !== 1,
      'ALARM_ACTION_COUNT',
    );
    reject(!/(?:Topic|:sns:)/.test(json(resolvedAlarmActions)), 'ALARM_ACTION_NON_SNS');
    reject(Array.isArray(resolvedOkActions) && resolvedOkActions.length > 0, 'OK_ACTION');
    reject(
      Array.isArray(resolvedInsufficientDataActions) && resolvedInsufficientDataActions.length > 0,
      'INSUFFICIENT_DATA_ACTION',
    );
    reject(
      FORBIDDEN_LOG_TEXT.test(
        json({
          description: alarm.alarmDescription,
          dimensions: alarm.dimensions,
          metrics: alarm.metrics,
        }),
      ),
      'ALARM_SENSITIVE_TEXT',
    );
    if (Array.isArray(alarm.dimensions)) {
      reject(
        alarm.dimensions.some(
          (dimension) =>
            !Token.isUnresolved(dimension) &&
            PERSONAL_DIMENSION.test((dimension as CfnAlarm.DimensionProperty).name),
        ),
        'PERSONAL_DIMENSION',
      );
    }
    if (alarm.namespace === 'AWS/CloudFront') {
      reject(Stack.of(alarm).region !== 'us-east-1', 'CLOUDFRONT_WRONG_REGION');
    }
    if (alarm.namespace === 'MXMed/Application') {
      reject(!this.config.applicationMetricEmissionIntegrated, 'APPLICATION_METRIC_NOT_INTEGRATED');
    }
  }

  private inspectDashboard(dashboard: CfnDashboard): void {
    const body = dashboard.dashboardBody;
    if (!Token.isUnresolved(body)) {
      reject(FORBIDDEN_LOG_TEXT.test(body), 'DASHBOARD_SENSITIVE_TEXT');
      reject(/log(?:s)?insights|LogQuery/i.test(body), 'DASHBOARD_LOG_WIDGET');
    }
  }

  private inspectLogGroup(logGroup: CfnLogGroup): void {
    if (!/(?:ApplicationLogGroup|MigrationLogGroup)/.test(logGroup.node.path)) return;
    const expected = this.config.environmentName === 'staging' ? 30 : 90;
    reject(logGroup.retentionInDays !== expected, 'WORKLOAD_LOG_RETENTION');
    reject(
      logGroup.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN,
      'WORKLOAD_LOG_REMOVAL_POLICY',
    );
  }

  private inspectForbiddenResource(resource: CfnResource): void {
    const forbiddenTypes = new Set([
      'AWS::Lambda::Function',
      'AWS::SSM::Association',
      'AWS::Synthetics::Canary',
      'AWS::RUM::AppMonitor',
      'AWS::XRay::Group',
      'AWS::OpenSearchService::Domain',
      'AWS::CloudFront::MonitoringSubscription',
    ]);
    reject(forbiddenTypes.has(resource.cfnResourceType), 'COSTLY_OR_AUTOMATION_RESOURCE');
    const propertiesText = json(resourceProperties(resource));
    reject(FORBIDDEN_LOG_TEXT.test(propertiesText), 'SENSITIVE_LOG_CONFIGURATION');
    reject(/\b[0-9]{12}\b/.test(propertiesText), 'LITERAL_ACCOUNT_ID');
  }
}
