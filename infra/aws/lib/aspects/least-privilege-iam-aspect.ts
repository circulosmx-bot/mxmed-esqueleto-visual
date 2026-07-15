import { Annotations, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnAccessKey, CfnManagedPolicy, CfnPolicy, CfnRole, CfnUser } from 'aws-cdk-lib/aws-iam';
import type { IConstruct } from 'constructs';

interface ResolvedStatement {
  readonly Effect?: unknown;
  readonly Action?: unknown;
  readonly Resource?: unknown;
  readonly Principal?: unknown;
  readonly Condition?: unknown;
}

function normalizedPath(node: IConstruct): string {
  return node.node.path.toLowerCase().replaceAll(/[^a-z0-9]/g, '');
}

function isWorkloadNode(node: IConstruct): boolean {
  const path = normalizedPath(node);
  return [
    'workload',
    'ecsexecutionrole',
    'applicationtaskrole',
    'migrationtaskrole',
    'jobstaskrole',
  ].some((marker) => path.includes(marker));
}

function statements(document: unknown, scope: IConstruct): ResolvedStatement[] {
  const resolved = Stack.of(scope).resolve(document) as { Statement?: unknown } | undefined;
  if (resolved === undefined || !Array.isArray(resolved.Statement)) return [];
  return resolved.Statement.filter(
    (statement): statement is ResolvedStatement =>
      typeof statement === 'object' && statement !== null,
  );
}

function stringValues(value: unknown): string[] {
  if (typeof value === 'string') return [value];
  if (Array.isArray(value))
    return value.filter((entry): entry is string => typeof entry === 'string');
  return [];
}

function isAllow(statement: ResolvedStatement): boolean {
  return statement.Effect === 'Allow';
}

function hasPublicPrincipal(statement: ResolvedStatement): boolean {
  if (!isAllow(statement)) return false;
  if (statement.Principal === '*') return true;
  return JSON.stringify(statement.Principal ?? {}).includes('"*"');
}

function hasWildcardSubject(statement: ResolvedStatement): boolean {
  if (!isAllow(statement)) return false;
  const condition = JSON.stringify(statement.Condition ?? {});
  return condition.includes('token.actions.githubusercontent.com:sub') && condition.includes('*');
}

function inspectPolicyDocument(node: IConstruct, document: unknown, workload: boolean): void {
  for (const statement of statements(document, node)) {
    const actions = stringValues(statement.Action);
    if (hasPublicPrincipal(statement)) {
      Annotations.of(node).addError('MXMED_IAM_PUBLIC_PRINCIPAL_FORBIDDEN');
    }
    if (hasWildcardSubject(statement)) {
      Annotations.of(node).addError('MXMED_IAM_OIDC_SUBJECT_WILDCARD_FORBIDDEN');
    }
    if (!isAllow(statement)) continue;
    if (actions.includes('iam:*')) {
      Annotations.of(node).addError('MXMED_IAM_WILDCARD_FORBIDDEN');
    }
    if (workload && actions.includes('kms:*')) {
      Annotations.of(node).addError('MXMED_WORKLOAD_KMS_WILDCARD_FORBIDDEN');
    }
    if (
      actions.includes('iam:PassRole') &&
      (statement.Resource === undefined || stringValues(statement.Resource).includes('*'))
    ) {
      Annotations.of(node).addError('MXMED_IAM_PASSROLE_UNRESTRICTED');
    }
  }
}

function isBoundaryRequired(role: CfnRole): boolean {
  const path = normalizedPath(role);
  return [
    'ecsexecutionrole',
    'applicationtaskrole',
    'migrationtaskrole',
    'jobstaskrole',
    'deploymentrole',
    'syntheticworkloadrole',
  ].some((marker) => path.includes(marker));
}

export class LeastPrivilegeIamAspect implements IAspect {
  public visit(node: IConstruct): void {
    if (node instanceof CfnUser) {
      Annotations.of(node).addError('MXMED_IAM_USER_FORBIDDEN');
      return;
    }
    if (node instanceof CfnAccessKey) {
      Annotations.of(node).addError('MXMED_IAM_ACCESS_KEY_FORBIDDEN');
      return;
    }
    if (node instanceof CfnRole) {
      const roleText = JSON.stringify(
        Stack.of(node).resolve({
          managedPolicyArns: node.managedPolicyArns,
          policies: node.policies,
        }),
      );
      if (roleText.includes('AdministratorAccess') || roleText.includes('PowerUserAccess')) {
        Annotations.of(node).addError('MXMED_IAM_AWS_MANAGED_ADMIN_POLICY_FORBIDDEN');
      }
      if (isBoundaryRequired(node) && node.permissionsBoundary === undefined) {
        Annotations.of(node).addError('MXMED_IAM_PERMISSION_BOUNDARY_REQUIRED');
      }
      inspectPolicyDocument(node, node.assumeRolePolicyDocument, isWorkloadNode(node));
      return;
    }
    if (node instanceof CfnManagedPolicy) {
      inspectPolicyDocument(node, node.policyDocument, isWorkloadNode(node));
      return;
    }
    if (node instanceof CfnPolicy) {
      inspectPolicyDocument(node, node.policyDocument, isWorkloadNode(node));
    }
  }
}
