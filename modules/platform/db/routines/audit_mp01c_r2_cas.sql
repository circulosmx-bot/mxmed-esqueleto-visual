-- Recovered accepted R2; only the installation account is parameterized.
DELIMITER $$

CREATE DEFINER = {{RESTRICTED_DEFINER}}
PROCEDURE `mxmed`.`audit_mp01c_advance_stream_head_cas_v1`(
    IN p_stream_key VARCHAR(191)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_expected_sequence BIGINT,
    IN p_expected_hash CHAR(64)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_expected_hash_version VARCHAR(32)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_expected_updated_at VARCHAR(27),
    IN p_new_sequence BIGINT,
    IN p_new_hash CHAR(64)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_new_hash_version VARCHAR(32)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    IN p_new_updated_at VARCHAR(27)
)
LANGUAGE SQL
NOT DETERMINISTIC
MODIFIES SQL DATA
SQL SECURITY DEFINER
COMMENT 'MXMed audit stream-head CAS v1; transaction-neutral'
BEGIN
    IF p_stream_key IS NULL
       OR CHAR_LENGTH(p_stream_key) = 0
       OR p_expected_sequence IS NULL
       OR p_expected_sequence < 0
       OR p_new_sequence IS NULL
       OR p_new_sequence <> p_expected_sequence + 1
       OR p_expected_hash IS NULL
       OR NOT REGEXP_LIKE(p_expected_hash, '^[a-f0-9]{64}$', 'c')
       OR p_new_hash IS NULL
       OR NOT REGEXP_LIKE(p_new_hash, '^[a-f0-9]{64}$', 'c')
       OR p_new_hash_version IS NULL
       OR p_new_hash_version <> 'sha256-hex-v1'
       OR p_new_updated_at IS NULL
       OR NOT REGEXP_LIKE(
              p_new_updated_at,
              '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{6}Z$',
              'c'
          )
       OR (
              p_expected_updated_at IS NOT NULL
              AND NOT REGEXP_LIKE(
                  p_expected_updated_at,
                  '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{6}Z$',
                  'c'
              )
          )
       OR (
              p_expected_sequence = 0
              AND (
                  p_expected_hash <> REPEAT('0', 64)
                  OR p_expected_hash_version IS NULL
                  OR p_expected_hash_version <> p_new_hash_version
                  OR p_expected_updated_at IS NOT NULL
              )
          )
       OR (
              p_expected_sequence > 0
              AND p_expected_updated_at IS NULL
          )
       OR (
              p_expected_hash_version IS NULL
              AND p_expected_sequence <= 0
          )
       OR (
              p_expected_hash_version IS NOT NULL
              AND p_expected_hash_version <> p_new_hash_version
          )
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'audit_stream_head_cas_invalid_input';
    END IF;

    UPDATE `mxmed`.`platform_audit_stream_heads`
       SET `last_sequence_number` = p_new_sequence,
           `last_event_hash` = p_new_hash,
           `hash_version` = p_new_hash_version,
           `updated_at` = STR_TO_DATE(
               p_new_updated_at,
               '%Y-%m-%dT%H:%i:%s.%fZ'
           )
     WHERE `stream_key` = p_stream_key
       AND `last_sequence_number` = p_expected_sequence
       AND `last_event_hash` = p_expected_hash
       AND `hash_version` <=> p_expected_hash_version
       AND `updated_at` <=> STR_TO_DATE(
           p_expected_updated_at,
           '%Y-%m-%dT%H:%i:%s.%fZ'
       );

    IF ROW_COUNT() <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'audit_stream_head_cas_mismatch';
    END IF;
END$$

DELIMITER ;
