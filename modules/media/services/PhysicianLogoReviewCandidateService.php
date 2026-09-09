<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/PhysicianMediaReviewCandidateService.php';
use Media\Contracts\PrivateMediaStoragePort;
use PDO;

final class PhysicianLogoReviewCandidateService
{
    public const MAX_BYTES = PhysicianMediaReviewCandidateService::MAX_BYTES;
    public const MAX_PIXELS = PhysicianMediaReviewCandidateService::MAX_PIXELS;
    public const MAX_SIDE = PhysicianMediaReviewCandidateService::MAX_SIDE;
    private PhysicianMediaReviewCandidateService $inner;
    public function __construct(PDO $pdo, PrivateMediaStoragePort $storage) {
        $this->inner = new PhysicianMediaReviewCandidateService($pdo, $storage, 'PHYSICIAN_PERSONAL_LOGO');
    }
    public function current(string $doctor): ?array { return $this->inner->current($doctor); }
    public function upload(string $doctor, array $upload): void { $this->inner->upload($doctor, $upload); }
    public function withdraw(string $doctor): void { $this->inner->withdraw($doctor); }
}
