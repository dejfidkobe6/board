-- Migration: Add Google OAuth support
-- Run once on existing installations (new installs use schema.sql which already has this)
ALTER TABLE `users`
  ADD COLUMN `google_id` VARCHAR(64) NULL DEFAULT NULL AFTER `reset_token_expires`,
  ADD UNIQUE KEY `uq_google_id` (`google_id`);
