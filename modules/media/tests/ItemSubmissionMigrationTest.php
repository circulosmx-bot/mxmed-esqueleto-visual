<?php
declare(strict_types=1);
require __DIR__.'/ProfilePhotoApprovalFixture.php';
$p=mr5Pdo();
// Test the real migration against temporary shadow tables, never Director records.
$p->exec("CREATE TEMPORARY TABLE mr02_legacy_batches(batch_id CHAR(36) PRIMARY KEY,status VARCHAR(16),submitted_at DATETIME(6))");
$p->exec("CREATE TEMPORARY TABLE mr02_legacy_items(submission_id VARCHAR(32) PRIMARY KEY,batch_id CHAR(36) NULL,technical_status VARCHAR(16),review_status VARCHAR(24),updated_at DATETIME(6))");
$p->exec("INSERT INTO mr02_legacy_batches VALUES('closed','SUBMITTED','2026-09-01 12:34:56.123456'),('open','OPEN',NULL)");
foreach([['closed-pending','closed','PENDING_REVIEW'],['closed-withdrawn','closed','WITHDRAWN'],['closed-approved','closed','APPROVED'],['open-pending','open','PENDING_REVIEW'],['legacy-unbatched',null,'PENDING_REVIEW']] as [$id,$batch,$status])$p->prepare("INSERT INTO mr02_legacy_items VALUES(?,?,'READY',?,'2026-08-01')")->execute([$id,$batch,$status]);
$sql=str_replace(['media_review_submissions','media_review_batches'],['mr02_legacy_items','mr02_legacy_batches'],file_get_contents(__DIR__.'/../db/migrations/2026_09_13_media_review_item_submission.sql'));
$p->exec($sql);$rows=$p->query('SELECT * FROM mr02_legacy_items ORDER BY submission_id')->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r){$expected=$r['submission_id']==='closed-pending'?'2026-09-01 12:34:56.123456':null;if($r['submitted_for_review_at']!==$expected||$r['updated_at']!=='2026-08-01 00:00:00.000000')throw new RuntimeException('legacy_history_fabricated');}
// Repeated backfill must not change timestamps or updated_at.
$p->exec(substr($sql,strpos($sql,'UPDATE mr02_legacy_items')));
if($p->query('SELECT * FROM mr02_legacy_items ORDER BY submission_id')->fetchAll(PDO::FETCH_ASSOC)!==$rows)throw new RuntimeException('backfill_not_idempotent');
echo "MR02_LEGACY_MIGRATION=PASS; reliable batch timestamp only; no fake dates; repeat backfill stable\n";
