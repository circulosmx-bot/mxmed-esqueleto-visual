-- Exact recovered semantic set: 17 privileges, 8 statements. No account provisioning.
GRANT INSERT, SELECT ON `mxmed`.`platform_audit_events`
  TO {{WRITER}};

GRANT INSERT, SELECT ON `mxmed`.`platform_audit_stream_heads`
  TO {{WRITER}};

GRANT EXECUTE ON PROCEDURE `mxmed`.`audit_mp01c_lock_stream_head_v1`
  TO {{WRITER}};

GRANT EXECUTE ON PROCEDURE `mxmed`.`audit_mp01c_advance_stream_head_cas_v1`
  TO {{WRITER}};

GRANT SELECT (
  `stream_key`, `last_sequence_number`, `last_event_hash`, `hash_version`, `updated_at`
) ON `mxmed`.`platform_audit_stream_heads`
  TO {{RESTRICTED_DEFINER}};

GRANT UPDATE (
  `last_sequence_number`, `last_event_hash`, `hash_version`, `updated_at`
) ON `mxmed`.`platform_audit_stream_heads`
  TO {{RESTRICTED_DEFINER}};

GRANT EXECUTE ON PROCEDURE `mxmed`.`audit_mp01c_lock_stream_head_v1`
  TO {{RESTRICTED_DEFINER}};

GRANT EXECUTE ON PROCEDURE `mxmed`.`audit_mp01c_advance_stream_head_cas_v1`
  TO {{RESTRICTED_DEFINER}};
