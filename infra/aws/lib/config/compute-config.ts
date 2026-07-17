import type {
  MxMedComputeActivationMode,
  MxMedDeploymentProfile,
  MxMedEnvironmentName,
  MxMedRuntimeCapabilityProfile,
} from './environment-config';
import { assertMxMedCondition } from '../utils/validation';

export const MXMED_COMPUTE_FOUNDATION_IMPLEMENTATION_CONTRACT =
  'MXMED_AWS_COMPUTE_FOUNDATION_IMPLEMENTATION_V1' as const;

export const MXMED_COMPUTE_ACTIVATION_MODES = [
  'disabled-v1',
  'registry-only-v1',
  'tasks-ready-v1',
  'service-enabled-v1',
] as const satisfies readonly MxMedComputeActivationMode[];

export const MXMED_RUNTIME_CAPABILITY_PROFILES = [
  'directory-core-v1',
  'paid-profile-v1',
  'clinical-v1',
  'professional-ai-v1',
] as const satisfies readonly MxMedRuntimeCapabilityProfile[];

export const MXMED_COMPUTE_RUNTIME_CONTRACT = Object.freeze({
  architecture: 'X86_64' as const,
  platformVersion: '1.4.0' as const,
  phpMajorVersion: '8.5' as const,
  apacheEnabled: true as const,
  modRewriteEnabled: true as const,
  documentRoot: '/var/www/html' as const,
  containerPort: 8080 as const,
  ephemeralStorageGiB: 20 as const,
  healthPath: '/healthz' as const,
  readinessPath: '/readyz' as const,
  cpuTargetPercent: 60 as const,
  memoryTargetPercent: 70 as const,
  scaleOutCooldownSeconds: 60 as const,
  scaleInCooldownSeconds: 300 as const,
  ecsExecEnabled: false as const,
  readonlyRootFilesystem: true as const,
  imageScanOnPush: true as const,
  imageTagImmutable: true as const,
  migrationCommandMode: 'fail-closed-v1' as const,
});

export interface MxMedResolvedComputeControls {
  readonly activationMode: MxMedComputeActivationMode;
  readonly runtimeCapabilityProfile: MxMedRuntimeCapabilityProfile | null;
}

export function parseComputeActivationMode(value: unknown): MxMedComputeActivationMode {
  assertMxMedCondition(
    MXMED_COMPUTE_ACTIVATION_MODES.includes(value as MxMedComputeActivationMode),
    'MXMED_CONFIG_INVALID',
    'computeActivationMode',
    'context must select an approved activation mode explicitly',
  );
  return value as MxMedComputeActivationMode;
}

export function parseRuntimeCapabilityProfile(value: unknown): MxMedRuntimeCapabilityProfile {
  assertMxMedCondition(
    MXMED_RUNTIME_CAPABILITY_PROFILES.includes(value as MxMedRuntimeCapabilityProfile),
    'MXMED_CONFIG_INVALID',
    'runtimeCapabilityProfile',
    'context must select an approved runtime capability profile explicitly',
  );
  return value as MxMedRuntimeCapabilityProfile;
}

export function resolveComputeControls(
  activationModeValue: unknown,
  runtimeCapabilityProfileValue: unknown,
): MxMedResolvedComputeControls {
  const activationMode = parseComputeActivationMode(activationModeValue);
  if (activationMode === 'disabled-v1' || activationMode === 'registry-only-v1') {
    return Object.freeze({ activationMode, runtimeCapabilityProfile: null });
  }
  return Object.freeze({
    activationMode,
    runtimeCapabilityProfile: parseRuntimeCapabilityProfile(runtimeCapabilityProfileValue),
  });
}

export function computeCreatesRegistry(mode: MxMedComputeActivationMode): boolean {
  return mode !== 'disabled-v1';
}

export function computeCreatesTasks(mode: MxMedComputeActivationMode): boolean {
  return mode === 'tasks-ready-v1' || mode === 'service-enabled-v1';
}

export function computeCreatesService(mode: MxMedComputeActivationMode): boolean {
  return mode === 'service-enabled-v1';
}

export function capabilityIncludesPaid(profile: MxMedRuntimeCapabilityProfile): boolean {
  return profile !== 'directory-core-v1';
}

export function capabilityIncludesClinical(profile: MxMedRuntimeCapabilityProfile): boolean {
  return profile === 'clinical-v1' || profile === 'professional-ai-v1';
}

export function capabilityIncludesAi(profile: MxMedRuntimeCapabilityProfile): boolean {
  return profile === 'professional-ai-v1';
}

export function computeEcrRetention(
  environment: MxMedEnvironmentName,
  deploymentProfile: MxMedDeploymentProfile,
): Readonly<{ untaggedDays: 7 | 14; maxImages: 20 | 50 }> {
  const lean = environment === 'staging' || deploymentProfile === 'launch-lean-v1';
  return lean
    ? Object.freeze({ untaggedDays: 7, maxImages: 20 })
    : Object.freeze({ untaggedDays: 14, maxImages: 50 });
}
