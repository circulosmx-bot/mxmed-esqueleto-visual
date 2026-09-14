<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/GallerySessionScope.php';
/** Construct only from server session or the canonical inactivity executor. */
final readonly class MediaSubmissionActor
{
    private function __construct(public string $doctor, public string $id, public ?string $session, public bool $system) {}
    public static function fromSession(array $session,string $sessionId): self
    {
        $scope=GallerySessionScope::resolve($session,false);
        if($scope===null || $sessionId==='')throw new \RuntimeException('submission_unauthorized');
        return new self($scope['doctor_id'],$scope['user_id'],$sessionId,false);
    }
    public static function executor(string $doctor): self
    {
        return new self($doctor,'media_review_batch_inactivity_executor',null,true);
    }
}
