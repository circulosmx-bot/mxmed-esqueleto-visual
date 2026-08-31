export const MXMED_C3_ACCOUNT = '875691018466' as const;
export const MXMED_C3_REGION = 'mx-central-1' as const;
export const MXMED_C3_BUDGETS_API_REGION = 'us-east-1' as const;
export const MXMED_C3_STACK_PREFIX = 'mxmed-stg-' as const;
export const MXMED_C3_COST_CAP_USD = 5 as const;
export const MXMED_C3_TEMPLATE_BODY_MAX_BYTES = 51_200 as const;
export const MXMED_C3_TEMPLATE_BUCKET_NAME =
  'mxmed-stg-c3-cf-templates-875691018466-mx-central-1' as const;
export const MXMED_C3_TEMPLATE_OBJECT_KEY_SUFFIX = '.template.json' as const;
export const MXMED_C3_AUDIT_BUCKET_NAME = 'mxmed-stg-audit-875691018466-mx-central-1' as const;
export const MXMED_C3_DEPLOYMENT_MODE = 'DIRECT_CLOUDFORMATION_FROM_SEALED_TEMPLATES' as const;
export const MXMED_C3_RUNTIME_CLOCK_ORIGIN = 'FIRST_SUCCESSFUL_RUNTIME_AWS_MUTATION' as const;
export const MXMED_C3_PENDING_RUNTIME_RESOLUTION = 'PENDING_RUNTIME_RESOLUTION' as const;
export const MXMED_C3_DIRECT_BUDGET_NAME_FORMAT = 'mxmed-stg-c3-${RUN_ID}' as const;
export const MXMED_C3_DIRECT_BUDGET_RUNTIME_OBJECT_COUNT = 1 as const;
export const MXMED_C3_BUDGET_ALERT_IS_REALTIME_FAILSAFE = false as const;
export const MXMED_C3_JANITOR_IS_REALTIME_FAILSAFE = true as const;

export type MxMedC3TemplateTransport = 'TEMPLATE_BODY' | 'C3_TEMPLATE_S3_URL';

export interface MxMedC3TemplateTransportCandidate {
  readonly account: string;
  readonly region: string;
  readonly bucketName: string;
  readonly publicAccessBlocked: boolean;
  readonly sealed: boolean;
  readonly expectedSha256: string;
  readonly actualSha256: string;
  readonly templateBytes: number;
  readonly transport: MxMedC3TemplateTransport;
  readonly templateText: string;
}

export function c3TemplateTransportForBytes(bytes: number): MxMedC3TemplateTransport {
  if (!Number.isInteger(bytes) || bytes <= 0) {
    throw new Error('MXMED_C3_TEMPLATE_BYTES_INVALID');
  }
  return bytes > MXMED_C3_TEMPLATE_BODY_MAX_BYTES ? 'C3_TEMPLATE_S3_URL' : 'TEMPLATE_BODY';
}

export function validateC3TemplateTransportCandidate(
  candidate: MxMedC3TemplateTransportCandidate,
): void {
  if (candidate.account !== MXMED_C3_ACCOUNT) throw new Error('MXMED_C3_ACCOUNT_MISMATCH');
  if (candidate.region !== MXMED_C3_REGION) throw new Error('MXMED_C3_REGION_MISMATCH');
  if (candidate.bucketName !== MXMED_C3_TEMPLATE_BUCKET_NAME) {
    throw new Error('MXMED_C3_TEMPLATE_BUCKET_MISMATCH');
  }
  if (!candidate.publicAccessBlocked) throw new Error('MXMED_C3_TEMPLATE_BUCKET_PUBLIC');
  if (!candidate.sealed) throw new Error('MXMED_C3_TEMPLATE_UNSEALED');
  if (candidate.expectedSha256 !== candidate.actualSha256) {
    throw new Error('MXMED_C3_TEMPLATE_HASH_MISMATCH');
  }
  if (
    /production|mxmed-prd-/i.test(candidate.templateText) ||
    /hnb659fds|\/cdk-bootstrap\/|CDKToolkit/.test(candidate.templateText) ||
    /["']BootstrapVersion["']|["']CheckBootstrapVersion["']/.test(candidate.templateText)
  ) {
    throw new Error('MXMED_C3_TEMPLATE_FORBIDDEN_AUTHORITY');
  }
  if (candidate.transport !== c3TemplateTransportForBytes(candidate.templateBytes)) {
    throw new Error('MXMED_C3_TEMPLATE_TRANSPORT_MISMATCH');
  }
}

export const MXMED_C3_RUNNER_CONTRACT = Object.freeze({
  cpuUnits: 256,
  memoryMiB: 512,
  taskTimeoutSeconds: 900,
  controllerWatchdogSeconds: 1200,
  teardownMaxDelaySeconds: 300,
  failsafeOffsetHours: 22,
  hardCapHours: 24,
  port: 6379,
  sessionSecretName: '/mxmed/staging/application/session-store-auth',
  sessionPrefix: 'mxmed:stg:session:',
  logGroupName: '/mxmed/staging/c3-runner',
  clusterName: 'mxmed-stg-c3-runner',
  taskFamily: 'mxmed-stg-c3-runner',
  applicationRepositoryName: 'mxmed-stg-application',
});

export const MXMED_C3_REQUIRED_TAGS = Object.freeze({
  Project: 'mxmed',
  Environment: 'staging',
  Phase: 'C3',
  Ephemeral: 'true',
});

export const MXMED_C3_STACK_NAMES = Object.freeze([
  'mxmed-stg-c3-janitor',
  'mxmed-stg-network',
  'mxmed-stg-security',
  'mxmed-stg-session',
  'mxmed-stg-registry',
  'mxmed-stg-c3-runner',
] as const);

export const MXMED_C3_DELETE_ORDER = Object.freeze([
  'mxmed-stg-c3-runner',
  'mxmed-stg-session',
  'mxmed-stg-registry',
  'mxmed-stg-security',
  'mxmed-stg-network',
] as const);

export const MXMED_C3_STOP_GATES = Object.freeze([
  'SOURCE_HEAD_MATCH',
  'WORKTREE_CLEAN',
  'FRESH_DIRECTOR_RUNTIME_AUTHORIZATION_PRESENT',
  'PRODUCTION_DENY_PROVEN',
  'SEALED_TEMPLATE_AND_RESOURCE_SCOPE_PASS',
  'ESTIMATED_COST_WITHIN_USD_5_CAP',
  'MANUAL_TEARDOWN_READY',
  'AUTO_TEARDOWN_FAILSAFE_CONTRACT_READY',
  'RETAINED_RESOURCE_CLEANUP_READY',
  'NONPRODUCTION_TARGET_PROVEN',
  'ROLE_CHAIN_EXACT_PASS',
  'ECR_DIGEST_SEALED_BEFORE_RUNNER',
] as const);

export const MXMED_C3_RUN_MANIFEST_REQUIRED_FIELDS = Object.freeze([
  'schema',
  'run_uuid',
  'run_id',
  'source_head',
  'account',
  'region',
  'director_authorization_reference',
  'activity_cost_cap_usd',
  'runtime_clock_contract',
  'pending_runtime_fields',
  'object_key_contract',
  'gate_definitions',
  'phase_requirements',
  'template_sha256',
  'templates',
  'source_sha256',
  'script_sha256',
  'policy_sha256',
  'image_build_inputs',
  'expected_resource_graph',
  'approved_role_profiles',
  'cfn_execution_role_arns',
  'stack_names',
  'expected_resource_counts',
  'retained_resource_expectations',
  'direct_budget_authority',
] as const);

export const MXMED_C3_RETAINED_LOGICAL_RESOURCES = Object.freeze([
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'ApplicationDataKeyC957928E',
    type: 'AWS::KMS::Key',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'SecretsKey317DCF94',
    type: 'AWS::KMS::Key',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'AuditKeyB2DBB069',
    type: 'AWS::KMS::Key',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'BackupKey60B97760',
    type: 'AWS::KMS::Key',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'SessionSigningSecret925D6419',
    type: 'AWS::SecretsManager::Secret',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'StripeSecretKeyContainerB8EBA645',
    type: 'AWS::SecretsManager::Secret',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'StripeWebhookSecretContainer9B02DE63',
    type: 'AWS::SecretsManager::Secret',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'AiApiKeyContainerC19542A6',
    type: 'AWS::SecretsManager::Secret',
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'AuditBucketB01E0AE8',
    type: 'AWS::S3::Bucket',
    physicalName: MXMED_C3_AUDIT_BUCKET_NAME,
  },
  {
    stackName: 'mxmed-stg-security',
    logicalId: 'CloudTrailLogGroup343A29D6',
    type: 'AWS::Logs::LogGroup',
  },
  {
    stackName: 'mxmed-stg-session',
    logicalId: 'SessionAuthSecretA6611D29',
    type: 'AWS::SecretsManager::Secret',
  },
  {
    stackName: 'mxmed-stg-registry',
    logicalId: 'RegistryKeyDD63DA09',
    type: 'AWS::KMS::Key',
  },
  {
    stackName: 'mxmed-stg-registry',
    logicalId: 'ApplicationRepository13E54097',
    type: 'AWS::ECR::Repository',
  },
] as const);

export const MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS = Object.freeze([
  'budgets:ModifyBudget',
  'budgets:ViewBudget',
] as const);

export const MXMED_C3_DIRECT_BUDGET_RESOURCE_PATTERN =
  'arn:aws:budgets::875691018466:budget/mxmed-stg-c3-*' as const;

export const MXMED_C3_DIRECT_BUDGET_SEMANTICS = Object.freeze({
  budgetType: 'COST',
  timeUnit: 'MONTHLY',
  budgetLimit: { amount: '5', unit: 'USD' },
  costFilters: { TagKeyValue: ['user:Phase$C3'] },
  thresholds: [1, 3, 5],
  thresholdType: 'ABSOLUTE_VALUE',
  notificationType: 'ACTUAL',
  comparisonOperator: 'GREATER_THAN',
  notificationTopicArn: 'arn:aws:sns:mx-central-1:875691018466:mxmed-stg-c3-notifications',
  usesResourceTags: false,
});

export const MXMED_C3_DIRECT_BUDGET_LEGACY_AWS_PORTAL_ACTIONS = Object.freeze([] as const);

export const MXMED_C3_DEPLOY_ACTIONS = Object.freeze([
  'cloudformation:CreateChangeSet',
  'cloudformation:DeleteChangeSet',
  'cloudformation:DescribeChangeSet',
  'cloudformation:DescribeStackEvents',
  'cloudformation:DescribeStackResources',
  'cloudformation:DescribeStacks',
  'cloudformation:ExecuteChangeSet',
  'cloudformation:GetTemplate',
  'cloudformation:GetTemplateSummary',
  'cloudformation:ListStackResources',
  'elasticache:DescribeReplicationGroups',
  'iam:PassRole',
  's3:CreateBucket',
  's3:DeleteObject',
  's3:GetBucketLocation',
  's3:GetBucketPolicy',
  's3:GetBucketPublicAccessBlock',
  's3:GetBucketTagging',
  's3:GetBucketVersioning',
  's3:GetEncryptionConfiguration',
  's3:GetObject',
  's3:GetObjectAttributes',
  's3:ListBucket',
  's3:PutBucketPolicy',
  's3:PutBucketPublicAccessBlock',
  's3:PutBucketTagging',
  's3:PutEncryptionConfiguration',
  's3:PutObject',
  'ecr:BatchCheckLayerAvailability',
  'ecr:CompleteLayerUpload',
  'ecr:GetAuthorizationToken',
  'ecr:InitiateLayerUpload',
  'ecr:PutImage',
  'ecr:UploadLayerPart',
  ...MXMED_C3_DIRECT_BUDGET_PROGRAMMATIC_ACTIONS,
] as const);

export const MXMED_C3_TEST_CONTROLLER_ACTIONS = Object.freeze([
  'ecs:RunTask',
  'ecs:DescribeTasks',
  'ecs:StopTask',
  'logs:DescribeLogStreams',
  'logs:GetLogEvents',
  'logs:FilterLogEvents',
  'logs:StartQuery',
  'logs:GetQueryResults',
  'iam:PassRole',
] as const);

export const MXMED_C3_TEARDOWN_ACTIONS = Object.freeze([
  'cloudformation:DeleteStack',
  'cloudformation:DescribeStacks',
  'cloudformation:DescribeStackEvents',
  'cloudformation:DescribeStackResources',
  'cloudformation:ListStackResources',
  'ecs:DescribeTasks',
  'ecs:StopTask',
  'ecs:DeregisterTaskDefinition',
  'ecs:DeleteCluster',
  'ecr:BatchDeleteImage',
  'ecr:ListImages',
  'ecr:DescribeImages',
  'ecr:DeleteRepository',
  'secretsmanager:DescribeSecret',
  'secretsmanager:DeleteSecret',
  'secretsmanager:ListSecrets',
  'cloudtrail:DescribeTrails',
  'cloudtrail:GetTrailStatus',
  'cloudtrail:StopLogging',
  'cloudtrail:DeleteTrail',
  's3:GetBucketLocation',
  's3:GetBucketVersioning',
  's3:ListBucket',
  's3:ListBucketVersions',
  's3:ListBucketMultipartUploads',
  's3:ListMultipartUploadParts',
  's3:DeleteObject',
  's3:DeleteObjectVersion',
  's3:AbortMultipartUpload',
  's3:DeleteBucketPolicy',
  's3:DeleteBucket',
  'logs:DescribeLogGroups',
  'logs:DeleteLogGroup',
  'kms:DescribeKey',
  'kms:ListAliases',
  'kms:ListResourceTags',
  'kms:DisableKey',
  'kms:ScheduleKeyDeletion',
  'scheduler:DeleteSchedule',
  'states:DeleteStateMachine',
  'budgets:ViewBudget',
  'budgets:ModifyBudget',
] as const);

export const MXMED_C3_UNSCOPABLE_ACTIONS = Object.freeze([
  'ecr:GetAuthorizationToken',
  's3:CreateBucket',
] as const);

export const MXMED_C3_EXPECTED_RESOURCE_TYPE_COUNTS = Object.freeze({
  'AWS::CloudTrail::Trail': 1,
  'AWS::EC2::EIP': 1,
  'AWS::EC2::FlowLog': 1,
  'AWS::EC2::InternetGateway': 1,
  'AWS::EC2::NatGateway': 1,
  'AWS::EC2::Route': 4,
  'AWS::EC2::RouteTable': 8,
  'AWS::EC2::SecurityGroup': 5,
  'AWS::EC2::SecurityGroupEgress': 10,
  'AWS::EC2::SecurityGroupIngress': 4,
  'AWS::EC2::Subnet': 8,
  'AWS::EC2::SubnetRouteTableAssociation': 8,
  'AWS::EC2::VPC': 1,
  'AWS::EC2::VPCEndpoint': 1,
  'AWS::EC2::VPCGatewayAttachment': 1,
  'AWS::ECR::Repository': 1,
  'AWS::ECS::Cluster': 1,
  'AWS::ECS::TaskDefinition': 1,
  'AWS::ElastiCache::ParameterGroup': 1,
  'AWS::ElastiCache::ReplicationGroup': 1,
  'AWS::ElastiCache::SubnetGroup': 1,
  'AWS::ElastiCache::User': 2,
  'AWS::ElastiCache::UserGroup': 1,
  'AWS::IAM::ManagedPolicy': 2,
  'AWS::IAM::Policy': 6,
  'AWS::IAM::Role': 10,
  'AWS::KMS::Alias': 5,
  'AWS::KMS::Key': 5,
  'AWS::Logs::LogGroup': 4,
  'AWS::S3::Bucket': 1,
  'AWS::S3::BucketPolicy': 1,
  'AWS::Scheduler::Schedule': 2,
  'AWS::SecretsManager::Secret': 5,
  'AWS::StepFunctions::StateMachine': 1,
});

export function expectedC3ResourceCount(): number {
  return Object.values(MXMED_C3_EXPECTED_RESOURCE_TYPE_COUNTS).reduce(
    (total, count) => total + count,
    0,
  );
}

export function totalAuthorizedC3RuntimeObjectCount(): number {
  return expectedC3ResourceCount() + MXMED_C3_DIRECT_BUDGET_RUNTIME_OBJECT_COUNT;
}

export const MXMED_C3_PERMISSION_BOUNDARY_ARN =
  'arn:aws:iam::875691018466:policy/MXMed-C3-Staging-PermissionBoundary' as const;

export const MXMED_C3_CONTROL_BOUNDARY_ARNS = Object.freeze({
  runtime: MXMED_C3_PERMISSION_BOUNDARY_ARN,
  deploy: 'arn:aws:iam::875691018466:policy/MXMed-C3-Staging-Deploy-Boundary',
  testController: 'arn:aws:iam::875691018466:policy/MXMed-C3-Staging-TestController-Boundary',
  teardown: 'arn:aws:iam::875691018466:policy/MXMed-C3-Staging-Teardown-Boundary',
} as const);

export const MXMED_C3_CFN_EXECUTION_ROLE_NAMES = Object.freeze({
  network: 'MXMed-C3-CFN-Network',
  security: 'MXMed-C3-CFN-Security',
  session: 'MXMed-C3-CFN-Session',
  registry: 'MXMed-C3-CFN-Registry',
  runner: 'MXMed-C3-CFN-Runner',
  janitor: 'MXMed-C3-CFN-Janitor',
} as const);

export const MXMED_C3_CFN_EXECUTION_ROLE_ARNS = Object.freeze(
  Object.fromEntries(
    Object.entries(MXMED_C3_CFN_EXECUTION_ROLE_NAMES).map(([stack, roleName]) => [
      stack,
      `arn:aws:iam::${MXMED_C3_ACCOUNT}:role/${roleName}`,
    ]),
  ) as Readonly<Record<keyof typeof MXMED_C3_CFN_EXECUTION_ROLE_NAMES, string>>,
);

export const MXMED_C3_CFN_EXECUTION_BOUNDARY_ARNS = Object.freeze(
  Object.fromEntries(
    Object.entries(MXMED_C3_CFN_EXECUTION_ROLE_NAMES).map(([stack, roleName]) => [
      stack,
      `arn:aws:iam::${MXMED_C3_ACCOUNT}:policy/${roleName}-Boundary`,
    ]),
  ) as Readonly<Record<keyof typeof MXMED_C3_CFN_EXECUTION_ROLE_NAMES, string>>,
);

/** Source authority for the three pre-existing human/control roles; this creates no IAM resource. */
export const MXMED_C3_CONTROL_ROLE_CONTRACTS = Object.freeze({
  deploy: {
    roleName: 'MXMed-C3-Staging-Deploy',
    permissionBoundaryArn: MXMED_C3_CONTROL_BOUNDARY_ARNS.deploy,
    actions: MXMED_C3_DEPLOY_ACTIONS,
    exactResourcePatterns: [
      'arn:aws:cloudformation:mx-central-1:875691018466:stack/mxmed-stg-*/*',
      'arn:aws:iam::875691018466:role/mxmed-stg-*',
      'arn:aws:iam::875691018466:policy/mxmed-stg-*',
      'arn:aws:ecr:mx-central-1:875691018466:repository/mxmed-stg-application',
      'arn:aws:logs:mx-central-1:875691018466:log-group:/mxmed/staging/*',
      'arn:aws:secretsmanager:mx-central-1:875691018466:secret:/mxmed/staging/*',
      'arn:aws:elasticache:mx-central-1:875691018466:replicationgroup:mxmed-stg-session',
      MXMED_C3_DIRECT_BUDGET_RESOURCE_PATTERN,
    ],
    explicitDenyPatterns: ['arn:aws:*:*:875691018466:*mxmed-prd-*', '*/mxmed/production/*'],
    unscopableActions: MXMED_C3_UNSCOPABLE_ACTIONS,
  },
  testController: {
    roleName: 'MXMed-C3-Staging-TestController',
    permissionBoundaryArn: MXMED_C3_CONTROL_BOUNDARY_ARNS.testController,
    actions: MXMED_C3_TEST_CONTROLLER_ACTIONS,
    exactResourcePatterns: [
      'arn:aws:ecs:mx-central-1:875691018466:cluster/mxmed-stg-c3-runner',
      'arn:aws:ecs:mx-central-1:875691018466:task-definition/mxmed-stg-c3-runner:*',
      'arn:aws:ecs:mx-central-1:875691018466:task/mxmed-stg-c3-runner/*',
      'arn:aws:logs:mx-central-1:875691018466:log-group:/mxmed/staging/c3-runner:*',
      'arn:aws:iam::875691018466:role/mxmed-stg-c3-runner-*',
    ],
    passRoleServices: ['ecs-tasks.amazonaws.com'],
    explicitDenyActions: [
      'cloudformation:*',
      'secretsmanager:GetSecretValue',
      'iam:Create*',
      'iam:Put*',
    ],
    explicitDenyPatterns: ['arn:aws:*:*:875691018466:*mxmed-prd-*', '*/mxmed/production/*'],
  },
  teardown: {
    roleName: 'MXMed-C3-Staging-Teardown',
    permissionBoundaryArn: MXMED_C3_CONTROL_BOUNDARY_ARNS.teardown,
    actions: MXMED_C3_TEARDOWN_ACTIONS,
    exactResourcePatterns: [
      'arn:aws:cloudformation:mx-central-1:875691018466:stack/mxmed-stg-*/*',
      'arn:aws:ecr:mx-central-1:875691018466:repository/mxmed-stg-application',
      'arn:aws:logs:mx-central-1:875691018466:log-group:/mxmed/staging/*',
      'arn:aws:secretsmanager:mx-central-1:875691018466:secret:/mxmed/staging/*',
      'arn:aws:kms:mx-central-1:875691018466:key/*',
    ],
    explicitDenyActions: [
      'cloudformation:CreateStack',
      'cloudformation:UpdateStack',
      'iam:Create*',
      'secretsmanager:GetSecretValue',
    ],
    explicitDenyPatterns: ['arn:aws:*:*:875691018466:*mxmed-prd-*', '*/mxmed/production/*'],
  },
});
