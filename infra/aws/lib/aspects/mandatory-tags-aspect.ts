import { Annotations, CfnResource, TagManager } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import type { IConstruct } from 'constructs';

import { MXMED_REQUIRED_RESOURCE_TAG_KEYS } from '../config/environment-config';

/** Non-taggable framework resources reviewed as carrying no independent business data. */
export const MXMED_NON_TAGGABLE_RESOURCE_ALLOWLIST = new Set(['AWS::CDK::Metadata']);

export class MandatoryTagsAspect implements IAspect {
  public visit(node: IConstruct): void {
    if (!(node instanceof CfnResource)) {
      return;
    }

    const tagManager = TagManager.of(node);
    if (tagManager === undefined) {
      if (!MXMED_NON_TAGGABLE_RESOURCE_ALLOWLIST.has(node.cfnResourceType)) {
        Annotations.of(node).addError('MXMED_NON_TAGGABLE_RESOURCE_REVIEW_REQUIRED');
      }
      return;
    }

    const tagValues = tagManager.tagValues();
    const missingTags = MXMED_REQUIRED_RESOURCE_TAG_KEYS.filter((tagKey) => {
      const tagValue = tagValues[tagKey];
      return typeof tagValue !== 'string' || tagValue.length === 0;
    });
    if (missingTags.length > 0) {
      Annotations.of(node).addError(`MXMED_MANDATORY_TAGS_MISSING:${missingTags.sort().join(',')}`);
    }
  }
}
