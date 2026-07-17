import { Duration, Fn, RemovalPolicy, Stack, Tags } from 'aws-cdk-lib';
import {
  Alarm,
  AlarmStatusWidget,
  ComparisonOperator,
  Dashboard,
  GraphWidget,
  MathExpression,
  Metric,
  TreatMissingData,
} from 'aws-cdk-lib/aws-cloudwatch';
import type { IMetric, IWidget } from 'aws-cdk-lib/aws-cloudwatch';
import { SnsAction } from 'aws-cdk-lib/aws-cloudwatch-actions';
import type { CfnDistribution } from 'aws-cdk-lib/aws-cloudfront';
import { Effect, PolicyStatement, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnTopic, Topic } from 'aws-cdk-lib/aws-sns';
import type { CfnWebACL } from 'aws-cdk-lib/aws-wafv2';
import type { Construct } from 'constructs';

import { operationsCreatesGlobalObservability } from '../config/operations-profiles';
import {
  MXMED_LAUNCH_LEAN_ALARM_CATALOG,
  MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
} from '../constructs/operations-alarm-catalog';
import type { MxMedAlarmDefinition } from '../constructs/operations-alarm-catalog';
import { MXMED_GLOBAL_DASHBOARD_CONTRACT } from '../constructs/operations-dashboard-contract';
import { mxmedName } from '../utils/naming';
import { buildOperationsAlarmDescription } from '../utils/operations-validation';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

export interface MxMedGlobalOperationsStackProps extends MxMedContractStackProps {
  readonly globalNotificationsKey: IKey;
  readonly distribution: CfnDistribution;
  readonly webAcl: CfnWebACL;
}

export class MxMedGlobalOperationsStack extends BaseMxMedStack {
  public readonly globalDashboard: Dashboard;
  public readonly globalEdgeAlertsTopic: Topic;
  public readonly globalAlarms: readonly Alarm[];

  private readonly mutableAlarms: Alarm[] = [];

  public constructor(scope: Construct, id: string, props: MxMedGlobalOperationsStackProps) {
    super(scope, id, {
      ...props,
      component: 'operations-global',
      description: 'MXMed us-east-1 Global Edge Operations foundation; deployment is external.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });
    if (!operationsCreatesGlobalObservability(props.config)) {
      throw new Error('MXMED_GLOBAL_OPERATIONS_DISABLED');
    }
    if (this.region !== 'us-east-1') throw new Error('MXMED_GLOBAL_OPERATIONS_REGION_INVALID');

    this.globalEdgeAlertsTopic = new Topic(this, 'GlobalEdgeAlertsTopic', {
      topicName: mxmedName(props.config.environmentCode, 'global-edge-alerts', 256),
      displayName: 'MXMed Global Edge alerts',
      masterKey: props.globalNotificationsKey,
    });
    const topicResource = this.globalEdgeAlertsTopic.node.defaultChild;
    if (!(topicResource instanceof CfnTopic))
      throw new Error('MXMED_GLOBAL_TOPIC_RESOURCE_INVALID');
    topicResource.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    this.addCloudWatchTopicPolicy();

    const metrics = this.createGlobalMetrics(props);
    this.createGlobalAlarms(props, metrics);
    this.globalAlarms = Object.freeze([...this.mutableAlarms]);
    this.globalDashboard = this.createDashboard(props, metrics);
    Tags.of(this).add('OperationsActivationMode', props.config.operationsActivationMode, {
      priority: 500,
    });
    Tags.of(this).add('AutomaticRemediation', 'disabled', { priority: 500 });
  }

  private addCloudWatchTopicPolicy(): void {
    this.globalEdgeAlertsTopic.addToResourcePolicy(
      new PolicyStatement({
        sid: 'CloudWatchAlarmPublish',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('cloudwatch.amazonaws.com')],
        actions: ['sns:Publish'],
        resources: [this.globalEdgeAlertsTopic.topicArn],
        conditions: {
          StringEquals: { 'aws:SourceAccount': Stack.of(this).account },
          ArnLike: {
            'aws:SourceArn': Fn.sub(
              'arn:${AWS::Partition}:cloudwatch:us-east-1:${AWS::AccountId}:alarm:*',
            ),
          },
        },
      }),
    );
  }

  private createGlobalMetrics(props: MxMedGlobalOperationsStackProps): Record<string, IMetric> {
    const cloudFrontDimensions = { DistributionId: props.distribution.ref, Region: 'Global' };
    if (props.webAcl.name === undefined) throw new Error('MXMED_GLOBAL_WAF_NAME_MISSING');
    const wafDimensions = { WebACL: props.webAcl.name, Region: 'Global', Rule: 'ALL' };
    return {
      cloudFrontRequests: this.metric(
        'AWS/CloudFront',
        'Requests',
        cloudFrontDimensions,
        'Sum',
        60,
      ),
      cloudFrontBytes: this.metric(
        'AWS/CloudFront',
        'BytesDownloaded',
        cloudFrontDimensions,
        'Sum',
        60,
      ),
      cloudFrontTotalErrors: this.metric(
        'AWS/CloudFront',
        'TotalErrorRate',
        cloudFrontDimensions,
        'Average',
        60,
      ),
      cloudFront5xx: this.metric(
        'AWS/CloudFront',
        '5xxErrorRate',
        cloudFrontDimensions,
        'Average',
        60,
      ),
      wafAllowed: this.metric('AWS/WAFV2', 'AllowedRequests', wafDimensions, 'Sum', 60),
      wafBlocked: this.metric('AWS/WAFV2', 'BlockedRequests', wafDimensions, 'Sum', 60),
      wafSensitive: this.metric(
        'AWS/WAFV2',
        'BlockedRequests',
        { ...wafDimensions, Rule: 'SensitiveRouteRateLimit' },
        'Sum',
        300,
      ),
      wafGeneral: this.metric(
        'AWS/WAFV2',
        'BlockedRequests',
        { ...wafDimensions, Rule: 'GeneralDynamicRateLimit' },
        'Sum',
        300,
      ),
    };
  }

  private createGlobalAlarms(
    props: MxMedGlobalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    const trafficReady =
      props.config.edgeActivationMode === 'public-traffic-enabled-v1' &&
      props.config.operationsRuntimeGateState === 'operational-readiness-integrated-v1';
    if (trafficReady) {
      const cloudFront5xx = new MathExpression({
        expression: 'IF(requests>=100,error5xx,0)',
        usingMetrics: {
          requests: this.requiredMetric(metrics, 'cloudFrontRequests'),
          error5xx: this.requiredMetric(metrics, 'cloudFront5xx'),
        },
        period: Duration.minutes(1),
        label: 'CloudFront 5xx gated rate',
      });
      this.createAlarm(
        props,
        this.definition('cloudfront-5xx-rate'),
        cloudFront5xx,
        1,
        5,
        5,
        'rate_ge_1pct_requests_ge_100_5m',
      );
      if (props.config.operationsActivationMode === 'production-observability-ready-v1') {
        const totalError = new MathExpression({
          expression: 'IF(requests>=100,totalError,0)',
          usingMetrics: {
            requests: this.requiredMetric(metrics, 'cloudFrontRequests'),
            totalError: this.requiredMetric(metrics, 'cloudFrontTotalErrors'),
          },
          period: Duration.minutes(1),
          label: 'CloudFront total error gated rate',
        });
        this.createAlarm(
          props,
          this.definition('cloudfront-total-error-rate'),
          totalError,
          5,
          5,
          5,
          'rate_ge_5pct_requests_ge_100_5m',
        );
      }
    }
    if (props.config.operationsActivationMode === 'production-observability-ready-v1') {
      this.createAlarm(
        props,
        this.definition('waf-sensitive-rate-spike'),
        this.requiredMetric(metrics, 'wafSensitive'),
        50,
        1,
        1,
        'blocked_ge_50_in_5m',
      );
      this.createAlarm(
        props,
        this.definition('waf-general-rate-spike'),
        this.requiredMetric(metrics, 'wafGeneral'),
        500,
        1,
        1,
        'blocked_ge_500_in_5m',
      );
    }
  }

  private createAlarm(
    props: MxMedGlobalOperationsStackProps,
    definition: MxMedAlarmDefinition,
    metric: IMetric,
    threshold: number,
    evaluationPeriods: number,
    datapointsToAlarm: number,
    thresholdSummary: string,
  ): void {
    const alarm = new Alarm(this, `${definition.id}Alarm`, {
      alarmName: mxmedName(props.config.environmentCode, definition.sanitizedCode, 255),
      alarmDescription: buildOperationsAlarmDescription(props.config, definition, thresholdSummary),
      metric,
      threshold,
      evaluationPeriods,
      datapointsToAlarm,
      comparisonOperator: ComparisonOperator.GREATER_THAN_OR_EQUAL_TO_THRESHOLD,
      treatMissingData: TreatMissingData.NOT_BREACHING,
    });
    alarm.addAlarmAction(new SnsAction(this.globalEdgeAlertsTopic));
    this.mutableAlarms.push(alarm);
  }

  private definition(id: string): MxMedAlarmDefinition {
    const definition = [
      ...MXMED_LAUNCH_LEAN_ALARM_CATALOG,
      ...MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
    ].find((entry) => entry.id === id);
    if (definition === undefined) throw new Error(`MXMED_GLOBAL_ALARM_UNKNOWN:${id}`);
    return definition;
  }

  private createDashboard(
    props: MxMedGlobalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): Dashboard {
    const dashboard = new Dashboard(this, 'GlobalEdgeDashboard', {
      dashboardName: mxmedName(
        props.config.environmentCode,
        MXMED_GLOBAL_DASHBOARD_CONTRACT.name,
        255,
      ),
      defaultInterval: Duration.hours(3),
    });
    const widgets = [
      this.graph('CloudFront requests / bytes', [
        this.requiredMetric(metrics, 'cloudFrontRequests'),
        this.requiredMetric(metrics, 'cloudFrontBytes'),
      ]),
      this.graph('CloudFront error rates', [
        this.requiredMetric(metrics, 'cloudFrontTotalErrors'),
        this.requiredMetric(metrics, 'cloudFront5xx'),
      ]),
      this.graph('WAF allowed / blocked', [
        this.requiredMetric(metrics, 'wafAllowed'),
        this.requiredMetric(metrics, 'wafBlocked'),
      ]),
      this.graph('WAF rate rules', [
        this.requiredMetric(metrics, 'wafSensitive'),
        this.requiredMetric(metrics, 'wafGeneral'),
      ]),
      new AlarmStatusWidget({ title: 'Global Edge alarm status', alarms: [...this.mutableAlarms] }),
    ];
    if (widgets.length > MXMED_GLOBAL_DASHBOARD_CONTRACT.maximumWidgets) {
      throw new Error('MXMED_GLOBAL_DASHBOARD_WIDGET_LIMIT');
    }
    dashboard.addWidgets(...(widgets as unknown as IWidget[]));
    return dashboard;
  }

  private graph(title: string, metrics: IMetric[]): GraphWidget {
    return new GraphWidget({
      title,
      left: metrics,
      period: Duration.minutes(1),
      width: 12,
      height: 6,
    });
  }

  private metric(
    namespace: string,
    metricName: string,
    dimensionsMap: Record<string, string>,
    statistic: string,
    periodSeconds: number,
  ): Metric {
    return new Metric({
      namespace,
      metricName,
      dimensionsMap,
      statistic,
      period: Duration.seconds(periodSeconds),
    });
  }

  private requiredMetric(metrics: Record<string, IMetric>, id: string): IMetric {
    const metric = metrics[id];
    if (metric === undefined) throw new Error(`MXMED_GLOBAL_METRIC_MISSING:${id}`);
    return metric;
  }
}
