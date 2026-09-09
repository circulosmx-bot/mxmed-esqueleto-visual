-- Recovered accepted R2; only the installation account is parameterized.
DELIMITER $$

CREATE DEFINER = {{RESTRICTED_DEFINER}}
PROCEDURE `mxmed`.`audit_mp01c_lock_stream_head_v1`(
    IN p_stream_key VARCHAR(191)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
)
LANGUAGE SQL
NOT DETERMINISTIC
READS SQL DATA
SQL SECURITY DEFINER
COMMENT 'MXMed audit stream-head locking read v1; transaction-neutral'
BEGIN
    IF p_stream_key IS NULL OR CHAR_LENGTH(p_stream_key) = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'audit_stream_head_lock_invalid_input';
    END IF;

    SELECT
        `last_sequence_number`,
        `last_event_hash`,
        `hash_version`,
        `updated_at`
    FROM `mxmed`.`platform_audit_stream_heads`
    WHERE `stream_key` = p_stream_key
    FOR UPDATE;
END$$

DELIMITER ;
