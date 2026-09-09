-- Additive MR7 constraint. Keep the existing active_photo_owner mechanism intact.
ALTER TABLE media_review_submissions
    ADD COLUMN active_logo_owner VARCHAR(191) GENERATED ALWAYS AS
      (CASE WHEN purpose='PHYSICIAN_PERSONAL_LOGO' AND review_status='PENDING_REVIEW'
       THEN owner_id ELSE NULL END) STORED,
    ADD UNIQUE KEY uniq_review_active_logo (owner_type, active_logo_owner);
