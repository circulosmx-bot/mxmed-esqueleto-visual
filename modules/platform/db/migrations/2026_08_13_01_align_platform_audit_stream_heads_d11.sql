-- AUDIT-MP01C D11 candidate only. STATIC/OFFLINE; not executed by this Preparation.
-- D11 preserves historical hash-version uncertainty as NULL and never rewrites history.
SET @d11_hash_version_column_count=(SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='platform_audit_stream_heads' AND column_name='hash_version');
SET @d11_hash_version_ddl=IF(@d11_hash_version_column_count=0,'ALTER TABLE platform_audit_stream_heads ADD COLUMN hash_version VARCHAR(32) NULL DEFAULT NULL AFTER last_event_hash','DO 0');
PREPARE d11_hash_version_stmt FROM @d11_hash_version_ddl;
EXECUTE d11_hash_version_stmt;
DEALLOCATE PREPARE d11_hash_version_stmt;

SET @d11_updated_at_column_count=(SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='platform_audit_stream_heads' AND column_name='updated_at');
SET @d11_updated_at_ddl=IF(@d11_updated_at_column_count=0,'ALTER TABLE platform_audit_stream_heads ADD COLUMN updated_at DATETIME(6) NULL DEFAULT NULL AFTER hash_version','DO 0');
PREPARE d11_updated_at_stmt FROM @d11_updated_at_ddl;
EXECUTE d11_updated_at_stmt;
DEALLOCATE PREPARE d11_updated_at_stmt;

DROP PROCEDURE IF EXISTS audit_mp01c_d11_align_stream_heads;
DELIMITER $$
CREATE PROCEDURE audit_mp01c_d11_align_stream_heads()
BEGIN
    DECLARE v_bad_columns BIGINT DEFAULT 0;
    DECLARE v_unmatched BIGINT DEFAULT 0;
    DECLARE v_ambiguous BIGINT DEFAULT 0;
    DECLARE v_invalid_empty BIGINT DEFAULT 0;
    DECLARE v_unknown_version BIGINT DEFAULT 0;
    DECLARE v_divergent_time BIGINT DEFAULT 0;
    DECLARE v_missing_time BIGINT DEFAULT 0;

    SELECT COUNT(*) INTO v_bad_columns
    FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='platform_audit_stream_heads'
      AND ((column_name='hash_version' AND NOT (column_type='varchar(32)' AND is_nullable='YES' AND column_default IS NULL))
        OR (column_name='updated_at' AND NOT (column_type='datetime(6)' AND is_nullable='YES' AND column_default IS NULL)));
    IF v_bad_columns<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_unexpected_column_state'; END IF;

    SELECT COUNT(*) INTO v_unmatched
    FROM platform_audit_stream_heads h
    LEFT JOIN platform_audit_events e
      ON h.stream_key=e.stream_key
     AND h.last_sequence_number=e.sequence_number
     AND h.last_event_hash=e.event_hash
    WHERE h.last_sequence_number>0 AND e.stream_key IS NULL;
    IF v_unmatched<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_unmatched_head_event'; END IF;

    SELECT COUNT(*) INTO v_ambiguous
    FROM (
        SELECT h.stream_key
        FROM platform_audit_stream_heads h
        JOIN platform_audit_events e
          ON h.stream_key=e.stream_key
         AND h.last_sequence_number=e.sequence_number
         AND h.last_event_hash=e.event_hash
        WHERE h.last_sequence_number>0
        GROUP BY h.stream_key
        HAVING COUNT(*)<>1
    ) ambiguous;
    IF v_ambiguous<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_ambiguous_head_event'; END IF;

    SELECT COUNT(*) INTO v_invalid_empty
    FROM platform_audit_stream_heads h
    WHERE h.last_sequence_number=0
      AND (h.last_event_hash<>REPEAT('0',64) OR h.hash_version IS NULL OR h.hash_version<>'sha256-hex-v1' OR h.updated_at IS NOT NULL);
    IF v_invalid_empty<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_invalid_empty_head'; END IF;

    SELECT COUNT(*) INTO v_unknown_version
    FROM platform_audit_stream_heads h
    WHERE h.hash_version IS NOT NULL AND h.hash_version<>'sha256-hex-v1';
    IF v_unknown_version<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_unknown_head_hash_version'; END IF;

    SELECT COUNT(*) INTO v_divergent_time
    FROM platform_audit_stream_heads h
    JOIN platform_audit_events e
      ON h.stream_key=e.stream_key
     AND h.last_sequence_number=e.sequence_number
     AND h.last_event_hash=e.event_hash
    WHERE h.last_sequence_number>0 AND h.updated_at IS NOT NULL AND h.updated_at<>e.created_at_utc;
    IF v_divergent_time<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_divergent_head_updated_at'; END IF;

    UPDATE platform_audit_stream_heads h
    JOIN platform_audit_events e
      ON h.stream_key=e.stream_key
     AND h.last_sequence_number=e.sequence_number
     AND h.last_event_hash=e.event_hash
    SET h.updated_at=e.created_at_utc
    WHERE h.last_sequence_number>0 AND h.updated_at IS NULL;

    SELECT COUNT(*) INTO v_missing_time
    FROM platform_audit_stream_heads h
    WHERE h.last_sequence_number>0 AND h.updated_at IS NULL;
    IF v_missing_time<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='d11_missing_head_updated_at'; END IF;
END$$
DELIMITER ;
CALL audit_mp01c_d11_align_stream_heads();
DROP PROCEDURE audit_mp01c_d11_align_stream_heads;
