<?php
declare(strict_types=1);
// Disposable fixture only; no application configuration or source DB connection.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);
$p=new PDO('mysql:host=127.0.0.1;port=3309','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$p->exec('USE mxmed');
$files=['modules/profiles/db/profiles_doctors_schema.sql','modules/media/db/migrations/2026_09_03_01_create_media_assets.sql','modules/media/db/migrations/2026_09_08_doctor_gallery.sql','modules/media/db/migrations/2026_09_08_doctor_profile_photo.sql','modules/media/db/migrations/2026_09_08_media_review_candidates.sql','modules/media/db/migrations/2026_09_08_media_review_pending_logo.sql','modules/media/db/migrations/2026_09_09_media_review_auto_proposal.sql','modules/media/db/migrations/2026_09_10_media_review_improvement_input.sql','modules/media/db/migrations/2026_09_11_media_review_replacement_feedback.sql','modules/media/db/migrations/2026_09_12_media_review_batches.sql'];
foreach($files as $file)$p->exec(file_get_contents($root.'/'.$file));
$sql=file_get_contents($root.'/modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql');
$p->exec(explode('DELIMITER',$sql)[0]);
$p->exec('CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32) NULL,updated_at DATETIME(6) NULL)');
$p->exec('CREATE PROCEDURE audit_mp01c_lock_stream_head_v1(IN k VARCHAR(255)) SELECT last_sequence_number,last_event_hash,hash_version,updated_at FROM platform_audit_stream_heads WHERE stream_key=k FOR UPDATE');
$p->exec("CREATE PROCEDURE audit_mp01c_advance_stream_head_cas_v1(IN k VARCHAR(255),IN seq BIGINT,IN h VARCHAR(64),IN v VARCHAR(64),IN t VARCHAR(32),IN ns BIGINT,IN nh VARCHAR(64),IN nv VARCHAR(64),IN nt VARCHAR(32)) BEGIN
 UPDATE platform_audit_stream_heads SET last_sequence_number=ns,last_event_hash=nh,hash_version=nv,updated_at=STR_TO_DATE(nt,'%Y-%m-%dT%H:%i:%s.%fZ')
 WHERE stream_key=k AND last_sequence_number=seq AND last_event_hash=h AND hash_version<=>v AND updated_at<=>STR_TO_DATE(t,'%Y-%m-%dT%H:%i:%s.%fZ');
 IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='head_update_failed'; END IF; END");
echo "DISPOSABLE_SCHEMA_READY\n";
