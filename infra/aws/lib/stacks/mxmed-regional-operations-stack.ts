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
import type { Cluster, FargateService } from 'aws-cdk-lib/aws-ecs';
import type {
  ApplicationLoadBalancer,
  ApplicationTargetGroup,
} from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import type { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import type { CfnReplicationGroup } from 'aws-cdk-lib/aws-elasticache';
import { Effect, PolicyStatement, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnTopic, Topic } from 'aws-cdk-lib/aws-sns';
import { SnsAction } from 'aws-cdk-lib/aws-cloudwatch-actions';
import type { Construct } from 'constructs';

import type {
  MxMedDeploymentProfile,
  MxMedEdgeActivationMode,
  MxMedRuntimeCapabilityProfile,
} from '../config/environment-config';
import { operationsCreatesObservability } from '../config/operations-profiles';
import {
  MXMED_LAUNCH_LEAN_ALARM_CATALOG,
  MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
  MXMED_RDS_INSTANCE_MEMORY_GIB,
  MXMED_VALKEY_CONNECTION_WARNING,
  deriveRdsConnectionBudget,
  storageThresholdBytes,
} from '../constructs/operations-alarm-catalog';
import type {
  MxMedAlarmDefinition,
  MxMedIncidentSeverity,
} from '../constructs/operations-alarm-catalog';
import { MXMED_REGIONAL_DASHBOARD_CONTRACT } from '../constructs/operations-dashboard-contract';
import { MXMED_OPERATIONS_RUNBOOK_CATALOG } from '../constructs/operations-runbook-catalog';
import { mxmedName } from '../utils/naming';
import { buildOperationsAlarmDescription } from '../utils/operations-validation';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

export interface MxMedRegionalOperationsStackProps extends MxMedContractStackProps {
  readonly auditKey: IKey;
  readonly databaseInstance: CfnDBInstance;
  readonly allocatedStorageGiB: number;
  readonly databaseInstanceClass: 'db.t4g.medium' | 'db.m6g.large';
  readonly replicationGroup: CfnReplicationGroup;
  readonly primaryCacheClusterId: string;
  readonly replicaCacheClusterId?: string;
  readonly sessionNodeType: 'cache.t4g.micro' | 'cache.t4g.medium';
  readonly cluster?: Cluster;
  readonly service?: FargateService;
  readonly deploymentProfile: MxMedDeploymentProfile;
  readonly computeMaxCapacity: number;
  readonly runtimeCapabilityProfile: MxMedRuntimeCapabilityProfile | null;
  readonly loadBalancer?: ApplicationLoadBalancer;
  readonly targetGroup?: ApplicationTargetGroup;
  readonly edgeActivationMode: MxMedEdgeActivationMode;
}

interface AlarmOptions {
  readonly definition: MxMedAlarmDefinition;
  readonly metric: IMetric;
  readonly threshold: number;
  readonly evaluationPeriods: number;
  readonly datapointsToAlarm?: number;
  readonly comparisonOperator?: ComparisonOperator;
  readonly thresholdSummary: string;
}

export class MxMedRegionalOperationsStack extends BaseMxMedStack {
  public readonly regionalDashboard: Dashboard;
  public readonly regionalCriticalTopic: Topic;
  public readonly regionalWarningTopic: Topic;
  public readonly regionalAlarms: readonly Alarm[];
  public readonly alarmCatalog: readonly MxMedAlarmDefinition[];
  public readonly runbookCatalog = MXMED_OPERATIONS_RUNBOOK_CATALOG;
  public readonly operationsProfile: MxMedRegionalOperationsStackProps['config']['operationsActivationMode'];

  private readonly mutableAlarms: Alarm[] = [];
  private readonly config: MxMedRegionalOperationsStackProps['config'];

  public constructor(scope: Construct, id: string, props: MxMedRegionalOperationsStackProps) {
    super(scope, id, {
      ...props,
      component: 'operations-regional',
      description: 'MXMed regional profile-aware Operations foundation; deployment is external.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });
    if (!operationsCreatesObservability(props.config)) {
      throw new Error('MXMED_REGIONAL_OPERATIONS_DISABLED');
    }
    if (this.region !== props.config.primaryRegion) {
      throw new Error('MXMED_REGIONAL_OPERATIONS_REGION_INVALID');
    }
    this.config = props.config;
    this.operationsProfile = props.config.operationsActivationMode;
    this.regionalCriticalTopic = this.createTopic(
      'RegionalCriticalTopic',
      'regional-critical-alerts',
      props.auditKey,
    );
    this.regionalWarningTopic = this.createTopic(
      'RegionalWarningTopic',
      'regional-warning-alerts',
      props.auditKey,
    );
    this.grantCloudWatchKeyUsage(props.auditKey);
    this.addCloudWatchTopicPolicy(this.regionalCriticalTopic);
    this.addCloudWatchTopicPolicy(this.regionalWarningTopic);

    const metrics = this.createAlarmCatalog(props);
    this.alarmCatalog = Object.freeze([
      ...MXMED_LAUNCH_LEAN_ALARM_CATALOG,
      ...(props.config.operationsActivationMode === 'production-observability-ready-v1'
        ? MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG
        : []),
    ]);
    this.regionalAlarms = Object.freeze([...this.mutableAlarms]);
    this.regionalDashboard = this.createDashboard(props, metrics);

    Tags.of(this).add('OperationsActivationMode', props.config.operationsActivationMode, {
      priority: 500,
    });
    Tags.of(this).add('AutomaticRemediation', 'disabled', { priority: 500 });

    void props.databaseInstance;
    void props.replicationGroup;
  }

  private createTopic(id: string, component: string, auditKey: IKey): Topic {
    const topic = new Topic(this, id, {
      topicName: mxmedName(this.config.environmentCode, component, 256),
      masterKey: auditKey,
    });
    const resource = topic.node.defaultChild;
    if (!(resource instanceof CfnTopic)) throw new Error('MXMED_REGIONAL_TOPIC_RESOURCE_INVALID');
    resource.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    return topic;
  }

  private grantCloudWatchKeyUsage(auditKey: IKey): void {
    auditKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'RegionalOperationsCloudWatchViaSns',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('cloudwatch.amazonaws.com')],
        actions: ['kms:GenerateDataKey*', 'kms:Decrypt'],
        resources: ['*'],
        conditions: {
          StringEquals: {
            'aws:SourceAccount': Stack.of(this).account,
            'kms:ViaService': `sns.${this.region}.amazonaws.com`,
          },
        },
      }),
    );
  }

  private addCloudWatchTopicPolicy(topic: Topic): void {
    topic.addToResourcePolicy(
      new PolicyStatement({
        sid: 'CloudWatchAlarmPublish',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('cloudwatch.amazonaws.com')],
        actions: ['sns:Publish'],
        resources: [topic.topicArn],
        conditions: {
          StringEquals: { 'aws:SourceAccount': Stack.of(this).account },
          ArnLike: {
            'aws:SourceArn': Fn.sub(
              `arn:\${AWS::Partition}:cloudwatch:${this.region}:\${AWS::AccountId}:alarm:*`,
            ),
          },
        },
      }),
    );
  }

  private createAlarmCatalog(props: MxMedRegionalOperationsStackProps): Record<string, IMetric> {
    const metrics: Record<string, IMetric> = {};
    this.createComputeAlarms(props, metrics);
    this.createRdsAlarms(props, metrics);
    this.createValkeyAlarms(props, metrics);
    this.createAlbAlarms(props, metrics);
    if (props.config.operationsActivationMode === 'production-observability-ready-v1') {
      this.createStandardRdsAlarms(props, metrics);
      this.createStandardValkeyAlarms(props, metrics);
      this.createStandardAlbAlarm(props, metrics);
    }
    return metrics;
  }

  private createComputeAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    if (props.cluster === undefined || props.service === undefined) return;
    const dimensions = {
      ClusterName: props.cluster.clusterName,
      ServiceName: props.service.serviceName,
    };
    const desired = new Metric({
      namespace: 'ECS/ContainerInsights',
      metricName: 'DesiredTaskCount',
      dimensionsMap: dimensions,
      statistic: 'Maximum',
      period: Duration.minutes(1),
    });
    const running = new Metric({
      namespace: 'ECS/ContainerInsights',
      metricName: 'RunningTaskCount',
      dimensionsMap: dimensions,
      statistic: 'Minimum',
      period: Duration.minutes(1),
    });
    const deficit = new MathExpression({
      expression: 'MAX([desired-running,0])',
      usingMetrics: { desired, running },
      period: Duration.minutes(1),
      label: 'Running task deficit',
    });
    metrics.ecsDesired = desired;
    metrics.ecsRunning = running;
    metrics.ecsDeficit = deficit;
    this.createAlarm(props, {
      definition: this.definition('ecs-task-deficit'),
      metric: deficit,
      threshold: 1,
      evaluationPeriods: 5,
      datapointsToAlarm: 5,
      thresholdSummary: 'deficit_ge_1_for_5m',
    });

    for (const [id, metricName, threshold, summary] of [
      [
        'ecs-high-cpu',
        'CPUUtilization',
        props.config.operationsEcsCpuWarningPercent,
        'avg_ge_75pct_15m',
      ],
      [
        'ecs-high-memory',
        'MemoryUtilization',
        props.config.operationsEcsMemoryWarningPercent,
        'avg_ge_80pct_15m',
      ],
    ] as const) {
      const metric = new Metric({
        namespace: 'AWS/ECS',
        metricName,
        dimensionsMap: dimensions,
        statistic: 'Average',
        period: Duration.minutes(5),
      });
      metrics[id] = metric;
      this.createAlarm(props, {
        definition: this.definition(id),
        metric,
        threshold,
        evaluationPeriods: 3,
        datapointsToAlarm: 3,
        thresholdSummary: summary,
      });
    }
  }

  private createRdsAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    const dimensions = { DBInstanceIdentifier: props.databaseInstance.ref };
    const cpu = this.metric('AWS/RDS', 'CPUUtilization', dimensions, 'Average', 300);
    const storage = this.metric('AWS/RDS', 'FreeStorageSpace', dimensions, 'Average', 300);
    const connections = this.metric('AWS/RDS', 'DatabaseConnections', dimensions, 'Maximum', 300);
    metrics.rdsCpu = cpu;
    metrics.rdsStorage = storage;
    metrics.rdsConnections = connections;
    this.createAlarm(props, {
      definition: this.definition('rds-high-cpu'),
      metric: cpu,
      threshold: props.config.operationsRdsCpuWarningPercent,
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: 'avg_ge_75pct_15m',
    });
    this.createAlarm(props, {
      definition: this.definition('rds-low-free-storage'),
      metric: storage,
      threshold: storageThresholdBytes(
        props.allocatedStorageGiB,
        props.config.operationsRdsFreeStoragePercent,
      ),
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      comparisonOperator: ComparisonOperator.LESS_THAN_OR_EQUAL_TO_THRESHOLD,
      thresholdSummary: 'free_lte_20pct_15m',
    });
    const connectionBudget = deriveRdsConnectionBudget(props.computeMaxCapacity);
    this.createAlarm(props, {
      definition: this.definition('rds-connection-budget'),
      metric: connections,
      threshold: connectionBudget.alarmThreshold,
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: `max_ge_70pct_budget_${String(connectionBudget.totalConnectionBudget)}`,
    });
  }

  private createValkeyAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    const dimensions = { CacheClusterId: props.primaryCacheClusterId };
    const evictions = this.metric('AWS/ElastiCache', 'Evictions', dimensions, 'Sum', 60);
    const memory = this.metric(
      'AWS/ElastiCache',
      'DatabaseMemoryUsagePercentage',
      dimensions,
      'Average',
      300,
    );
    metrics.valkeyEvictions = evictions;
    metrics.valkeyMemory = memory;
    this.createAlarm(props, {
      definition: this.definition('valkey-evictions'),
      metric: evictions,
      threshold: 0,
      evaluationPeriods: 5,
      datapointsToAlarm: 1,
      comparisonOperator: ComparisonOperator.GREATER_THAN_THRESHOLD,
      thresholdSummary: 'sum_gt_0_in_5m',
    });
    this.createAlarm(props, {
      definition: this.definition('valkey-memory-pressure'),
      metric: memory,
      threshold: props.config.operationsValkeyMemoryWarningPercent,
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: 'avg_ge_75pct_15m',
    });
  }

  private createAlbAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    if (
      props.loadBalancer === undefined ||
      props.targetGroup === undefined ||
      props.config.operationsRuntimeGateState !== 'operational-readiness-integrated-v1'
    ) {
      return;
    }
    const dimensions = {
      LoadBalancer: props.loadBalancer.loadBalancerFullName,
      TargetGroup: props.targetGroup.targetGroupFullName,
    };
    const unhealthy = this.metric(
      'AWS/ApplicationELB',
      'UnHealthyHostCount',
      dimensions,
      'Maximum',
      60,
    );
    const requests = this.metric('AWS/ApplicationELB', 'RequestCount', dimensions, 'Sum', 60);
    const target5xx = this.metric(
      'AWS/ApplicationELB',
      'HTTPCode_Target_5XX_Count',
      dimensions,
      'Sum',
      60,
    );
    const rate = new MathExpression({
      expression: 'IF(requests>=20,target5xx/requests*100,0)',
      usingMetrics: { requests, target5xx },
      period: Duration.minutes(1),
      label: 'Target 5xx rate',
    });
    metrics.albUnhealthy = unhealthy;
    metrics.albRequests = requests;
    metrics.albTarget5xx = target5xx;
    metrics.albTarget5xxRate = rate;
    this.createAlarm(props, {
      definition: this.definition('alb-unhealthy-target'),
      metric: unhealthy,
      threshold: 1,
      evaluationPeriods: 3,
      datapointsToAlarm: 2,
      thresholdSummary: 'max_ge_1_2of3m',
    });
    this.createAlarm(props, {
      definition: this.definition('alb-target-5xx-rate'),
      metric: rate,
      threshold: props.config.operationsAlbTarget5xxRatePercent,
      evaluationPeriods: 5,
      datapointsToAlarm: 5,
      thresholdSummary: 'rate_ge_2pct_requests_ge_20_5m',
    });
  }

  private createStandardRdsAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    const dimensions = { DBInstanceIdentifier: props.databaseInstance.ref };
    const memory = this.metric('AWS/RDS', 'FreeableMemory', dimensions, 'Average', 300);
    const readLatency = this.metric('AWS/RDS', 'ReadLatency', dimensions, 'Average', 300);
    const writeLatency = this.metric('AWS/RDS', 'WriteLatency', dimensions, 'Average', 300);
    const queue = this.metric('AWS/RDS', 'DiskQueueDepth', dimensions, 'Average', 300);
    const storage = this.requiredMetric(metrics, 'rdsStorage');
    metrics.rdsMemory = memory;
    metrics.rdsReadLatency = readLatency;
    metrics.rdsWriteLatency = writeLatency;
    metrics.rdsDiskQueue = queue;
    const memoryBytes =
      MXMED_RDS_INSTANCE_MEMORY_GIB[props.databaseInstanceClass] * 1024 ** 3 * 0.2;
    for (const options of [
      {
        id: 'rds-low-free-memory',
        metric: memory,
        threshold: memoryBytes,
        comparison: ComparisonOperator.LESS_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'free_lte_20pct_contract_memory',
      },
      {
        id: 'rds-read-latency',
        metric: readLatency,
        threshold: 0.02,
        comparison: ComparisonOperator.GREATER_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'avg_ge_20ms_15m',
      },
      {
        id: 'rds-write-latency',
        metric: writeLatency,
        threshold: 0.02,
        comparison: ComparisonOperator.GREATER_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'avg_ge_20ms_15m',
      },
      {
        id: 'rds-disk-queue',
        metric: queue,
        threshold: 10,
        comparison: ComparisonOperator.GREATER_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'avg_ge_10_15m',
      },
      {
        id: 'rds-storage-warning',
        metric: storage,
        threshold: storageThresholdBytes(props.allocatedStorageGiB, 30),
        comparison: ComparisonOperator.LESS_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'free_lte_30pct_15m',
      },
      {
        id: 'rds-storage-critical',
        metric: storage,
        threshold: storageThresholdBytes(props.allocatedStorageGiB, 15),
        comparison: ComparisonOperator.LESS_THAN_OR_EQUAL_TO_THRESHOLD,
        summary: 'free_lte_15pct_15m',
      },
    ] as const) {
      this.createAlarm(props, {
        definition: this.definition(options.id),
        metric: options.metric,
        threshold: options.threshold,
        comparisonOperator: options.comparison,
        evaluationPeriods: 3,
        datapointsToAlarm: 3,
        thresholdSummary: options.summary,
      });
    }
  }

  private createStandardValkeyAlarms(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    const primaryDimensions = { CacheClusterId: props.primaryCacheClusterId };
    const cpu = this.metric('AWS/ElastiCache', 'CPUUtilization', primaryDimensions, 'Average', 300);
    const connections = this.metric(
      'AWS/ElastiCache',
      'CurrConnections',
      primaryDimensions,
      'Maximum',
      300,
    );
    metrics.valkeyCpu = cpu;
    metrics.valkeyConnections = connections;
    this.createAlarm(props, {
      definition: this.definition('valkey-high-cpu'),
      metric: cpu,
      threshold: 75,
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: 'avg_ge_75pct_15m',
    });
    this.createAlarm(props, {
      definition: this.definition('valkey-connections'),
      metric: connections,
      threshold: MXMED_VALKEY_CONNECTION_WARNING[props.sessionNodeType],
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: `max_ge_${String(MXMED_VALKEY_CONNECTION_WARNING[props.sessionNodeType])}_15m`,
    });
    if (props.replicaCacheClusterId !== undefined) {
      const lag = this.metric(
        'AWS/ElastiCache',
        'ReplicationLag',
        { CacheClusterId: props.replicaCacheClusterId },
        'Maximum',
        60,
      );
      metrics.valkeyReplicaLag = lag;
      this.createAlarm(props, {
        definition: this.definition('valkey-replication-lag'),
        metric: lag,
        threshold: 1,
        evaluationPeriods: 5,
        datapointsToAlarm: 3,
        thresholdSummary: 'max_ge_1s_3of5m',
      });
    }
  }

  private createStandardAlbAlarm(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): void {
    if (
      props.loadBalancer === undefined ||
      props.targetGroup === undefined ||
      props.config.operationsRuntimeGateState !== 'operational-readiness-integrated-v1'
    ) {
      return;
    }
    const latency = this.metric(
      'AWS/ApplicationELB',
      'TargetResponseTime',
      {
        LoadBalancer: props.loadBalancer.loadBalancerFullName,
        TargetGroup: props.targetGroup.targetGroupFullName,
      },
      'p95',
      300,
    );
    metrics.albLatency = latency;
    this.createAlarm(props, {
      definition: this.definition('alb-target-response-p95'),
      metric: latency,
      threshold: 2,
      evaluationPeriods: 3,
      datapointsToAlarm: 3,
      thresholdSummary: 'p95_ge_2s_15m',
    });
  }

  private createAlarm(props: MxMedRegionalOperationsStackProps, options: AlarmOptions): Alarm {
    const alarm = new Alarm(this, `${options.definition.id}Alarm`, {
      alarmName: mxmedName(props.config.environmentCode, options.definition.sanitizedCode, 255),
      alarmDescription: buildOperationsAlarmDescription(
        props.config,
        options.definition,
        options.thresholdSummary,
      ),
      metric: options.metric,
      threshold: options.threshold,
      evaluationPeriods: options.evaluationPeriods,
      ...(options.datapointsToAlarm === undefined
        ? {}
        : { datapointsToAlarm: options.datapointsToAlarm }),
      comparisonOperator:
        options.comparisonOperator ?? ComparisonOperator.GREATER_THAN_OR_EQUAL_TO_THRESHOLD,
      treatMissingData: TreatMissingData.NOT_BREACHING,
    });
    const topic = this.topicForSeverity(options.definition.severity);
    if (topic !== undefined) alarm.addAlarmAction(new SnsAction(topic));
    this.mutableAlarms.push(alarm);
    return alarm;
  }

  private topicForSeverity(severity: MxMedIncidentSeverity): Topic | undefined {
    if (severity === 'SEV1' || severity === 'SEV2') return this.regionalCriticalTopic;
    if (severity === 'SEV3') return this.regionalWarningTopic;
    return undefined;
  }

  private definition(id: string): MxMedAlarmDefinition {
    const definition = [
      ...MXMED_LAUNCH_LEAN_ALARM_CATALOG,
      ...MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
    ].find((entry) => entry.id === id);
    if (definition === undefined) throw new Error(`MXMED_OPERATIONS_ALARM_UNKNOWN:${id}`);
    return definition;
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

  private createDashboard(
    props: MxMedRegionalOperationsStackProps,
    metrics: Record<string, IMetric>,
  ): Dashboard {
    const dashboard = new Dashboard(this, 'RegionalOperationsDashboard', {
      dashboardName: mxmedName(
        props.config.environmentCode,
        MXMED_REGIONAL_DASHBOARD_CONTRACT.name,
        255,
      ),
      defaultInterval: Duration.hours(3),
    });
    const widgets: (GraphWidget | AlarmStatusWidget)[] = [];
    if (metrics.ecsDesired !== undefined && metrics.ecsRunning !== undefined) {
      widgets.push(
        this.graph('ECS desired / running tasks', [metrics.ecsDesired, metrics.ecsRunning]),
      );
    }
    if (metrics['ecs-high-cpu'] !== undefined && metrics['ecs-high-memory'] !== undefined) {
      widgets.push(
        this.graph('ECS CPU / memory', [metrics['ecs-high-cpu'], metrics['ecs-high-memory']]),
      );
    }
    if (metrics.albUnhealthy !== undefined)
      widgets.push(this.graph('ALB target health', [metrics.albUnhealthy]));
    if (metrics.albRequests !== undefined && metrics.albTarget5xx !== undefined) {
      widgets.push(
        this.graph('ALB requests / target 5xx', [metrics.albRequests, metrics.albTarget5xx]),
      );
    }
    widgets.push(
      this.graph('RDS CPU / connections', [
        this.requiredMetric(metrics, 'rdsCpu'),
        this.requiredMetric(metrics, 'rdsConnections'),
      ]),
    );
    widgets.push(
      this.graph(
        'RDS free storage / memory',
        [this.requiredMetric(metrics, 'rdsStorage'), metrics.rdsMemory].filter(
          (metric): metric is IMetric => metric !== undefined,
        ),
      ),
    );
    widgets.push(
      this.graph(
        'Valkey memory / evictions / connections',
        [
          this.requiredMetric(metrics, 'valkeyMemory'),
          this.requiredMetric(metrics, 'valkeyEvictions'),
          metrics.valkeyConnections,
        ].filter((metric): metric is IMetric => metric !== undefined),
      ),
    );
    widgets.push(
      new AlarmStatusWidget({ title: 'Regional alarm status', alarms: [...this.mutableAlarms] }),
    );
    if (widgets.length > MXMED_REGIONAL_DASHBOARD_CONTRACT.maximumWidgets) {
      throw new Error('MXMED_REGIONAL_DASHBOARD_WIDGET_LIMIT');
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

  private requiredMetric(metrics: Record<string, IMetric>, id: string): IMetric {
    const metric = metrics[id];
    if (metric === undefined) throw new Error(`MXMED_OPERATIONS_METRIC_MISSING:${id}`);
    return metric;
  }
}
