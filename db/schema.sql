-- BeSix Multi-App Database Schema
-- Encoding: UTF-8mb4
-- Run this script once on your MySQL/MariaDB server

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ─────────────────────────────────────────
-- 1. USERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`                VARCHAR(120)     NOT NULL,
  `email`               VARCHAR(180)     NOT NULL,
  `password_hash`       VARCHAR(255)     NOT NULL,
  `avatar_color`        VARCHAR(10)      NOT NULL DEFAULT '#4A5340',
  `is_verified`         TINYINT(1)       NOT NULL DEFAULT 0,
  `verification_token`  VARCHAR(64)          NULL DEFAULT NULL,
  `reset_token`         VARCHAR(64)          NULL DEFAULT NULL,
  `reset_token_expires` TIMESTAMP            NULL DEFAULT NULL,
  `google_id`           VARCHAR(64)          NULL DEFAULT NULL,
  `created_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_google_id` (`google_id`),
  KEY `idx_verification_token` (`verification_token`),
  KEY `idx_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- 2. APPS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `apps` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_key`     VARCHAR(50)  NOT NULL,
  `app_name`    VARCHAR(120) NOT NULL,
  `description` TEXT             NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_key` (`app_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `apps` (`app_key`, `app_name`, `description`) VALUES
  ('stavbaboard', 'StavbaBoard',  'Kanban pro stavební tým'),
  ('plans',       'BeSix Plans',  'Správa půdorysů');

-- ─────────────────────────────────────────
-- 3. PROJECTS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projects` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id`      INT UNSIGNED NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `description` TEXT             NULL,
  `created_by`  INT UNSIGNED NOT NULL,
  `invite_code` VARCHAR(32)  NOT NULL,
  `bg_color`    VARCHAR(10)      NULL DEFAULT '#4a5240',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite_code` (`invite_code`),
  KEY `idx_app_id` (`app_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_projects_app`  FOREIGN KEY (`app_id`)     REFERENCES `apps`  (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- 4. PROJECT_MEMBERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `project_members` (
  `id`         INT UNSIGNED                              NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED                             NOT NULL,
  `user_id`    INT UNSIGNED                             NOT NULL,
  `role`       ENUM('owner','admin','member','viewer')  NOT NULL DEFAULT 'member',
  `invited_by` INT UNSIGNED                                 NULL DEFAULT NULL,
  `joined_at`  TIMESTAMP                                NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_pm_project`  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_user`     FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_inviter`  FOREIGN KEY (`invited_by`) REFERENCES `users`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- 5. INVITATIONS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invitations` (
  `id`             INT UNSIGNED                         NOT NULL AUTO_INCREMENT,
  `project_id`     INT UNSIGNED                         NOT NULL,
  `invited_email`  VARCHAR(180)                         NOT NULL,
  `invited_by`     INT UNSIGNED                         NOT NULL,
  `token`          VARCHAR(64)                          NOT NULL,
  `role`           ENUM('admin','member','viewer')      NOT NULL DEFAULT 'member',
  `status`         ENUM('pending','accepted','expired') NOT NULL DEFAULT 'pending',
  `expires_at`     TIMESTAMP                            NOT NULL,
  `created_at`     TIMESTAMP                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_invited_email` (`invited_email`),
  CONSTRAINT `fk_inv_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
