-- Add the profile photograph purpose; preserve gallery and logo contracts.
ALTER TABLE media_assets MODIFY purpose ENUM('PHYSICIAN_PERSONAL_LOGO','CONSULTORIO_GROUP_LOGO','DOCTOR_GALLERY','DOCTOR_PROFILE_PHOTO') NOT NULL;
