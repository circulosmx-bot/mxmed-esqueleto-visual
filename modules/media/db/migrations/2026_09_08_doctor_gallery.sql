-- Additive: retain both existing logo purposes and their constraints.
ALTER TABLE media_assets MODIFY purpose ENUM('PHYSICIAN_PERSONAL_LOGO','CONSULTORIO_GROUP_LOGO','DOCTOR_GALLERY') NOT NULL;
