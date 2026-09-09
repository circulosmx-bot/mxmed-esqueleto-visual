<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAudit.php';
use Platform\Contracts\{AuditTrailPort,AuditEventReference,TrustedAuthorizationContext};

/** MR5 binding to the shared fail-closed, caller-transaction canonical producer. */
final class ProfilePhotoApprovalAudit implements AuditTrailPort
{
    private MediaReviewAudit $inner;
    public function __construct(\PDO $pdo, TrustedAuthorizationContext $context, \Closure $prepare)
    {
        $this->inner=new MediaReviewAudit($pdo,$context,$prepare,'MEDIA_PROFILE_PHOTO_APPROVED','media_review_approve','POST /api/internal/media-review/approve.php');
    }
    public function availability(): string { return $this->inner->availability(); }
    public function write(AuditEventReference $event): string { return $this->inner->write($event); }
}
