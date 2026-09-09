<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ob_start();require __DIR__.'/MediaReviewReadSnapshot.php';$after=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
$before=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$rows=$p->query('SELECT * FROM media_review_submissions ORDER BY submission_id')->fetchAll();
foreach($rows as &$row){if(($row['batch_id']??null)!==null)throw new RuntimeException('real_submission_batched');unset($row['batch_id']);}unset($row);
$after['tables']['media_review_submissions']=hash('sha256',json_encode($rows));
if($before!==$after)throw new RuntimeException('Director_baseline_changed');
foreach(['media_review_batches','media_review_batch_ready_events'] as $t)if((int)$p->query("SELECT COUNT(*) FROM $t")->fetchColumn()!==0)throw new RuntimeException('real_batch_created');
echo "DIRECTOR_BASELINE=UNCHANGED; existing rows/files exact; nullable batch_id only; no real batches/signals\n";
