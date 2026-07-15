import { Annotations, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import type { IConstruct } from 'constructs';

export interface MxMedStripeReturnLoggingControls {
  readonly policy: string;
  readonly queryFieldsExcluded: boolean;
  readonly refererExcluded: boolean;
  readonly cookieExcluded: boolean;
  readonly fullRequestLineExcluded: boolean;
  readonly cacheDisabled: boolean;
  readonly wafQueryRedactionRequired: boolean;
}

export const MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS = Object.freeze({
  policy: 'path-only-no-query',
  queryFieldsExcluded: true,
  refererExcluded: true,
  cookieExcluded: true,
  fullRequestLineExcluded: true,
  cacheDisabled: true,
  wafQueryRedactionRequired: true,
} satisfies MxMedStripeReturnLoggingControls);

export class StripeReturnLoggingSafetyAspect implements IAspect {
  public constructor(private readonly controls: MxMedStripeReturnLoggingControls) {}

  public visit(node: IConstruct): void {
    if (!(node instanceof Stack)) {
      return;
    }

    const safe =
      this.controls.policy === 'path-only-no-query' &&
      this.controls.queryFieldsExcluded &&
      this.controls.refererExcluded &&
      this.controls.cookieExcluded &&
      this.controls.fullRequestLineExcluded &&
      this.controls.cacheDisabled &&
      this.controls.wafQueryRedactionRequired;

    if (!safe) {
      Annotations.of(node).addError('MXMED_STRIPE_RETURN_LOGGING_POLICY_UNSAFE');
    }
  }
}
