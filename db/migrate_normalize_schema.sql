-- Migration: create normalized board schema
-- Vytvoří nové prázdné tabulky vedle stávajících board_project_kanban_state
-- a board_project_meeting_state. Žádná stará data NEMĚNÍ ani NEMAŽE.
--
-- Bezpečné na opakované spuštění (CREATE TABLE IF NOT EXISTS).
-- Pusť v phpMyAdminu (záložka SQL).

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─────────────────────────────────────────
-- SLOUPCE KANBANU
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_columns` (
  `id`         VARCHAR(50)  NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL,
  `color`      VARCHAR(20)      NULL,
  `archived`   TINYINT(1)   NOT NULL DEFAULT 0,
  `position`   INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_position` (`project_id`, `position`),
  CONSTRAINT `fk_bc_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- KARTY
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_cards` (
  `id`          VARCHAR(50)  NOT NULL,
  `project_id`  INT UNSIGNED NOT NULL,
  `column_id`   VARCHAR(50)  NOT NULL,
  `title`       VARCHAR(500) NOT NULL,
  `description` MEDIUMTEXT       NULL,
  `priority`    ENUM('low','med','high') NOT NULL DEFAULT 'low',
  `deadline`    DATE             NULL,
  `tag`         VARCHAR(100)     NULL,
  `cover_url`   VARCHAR(500)     NULL,
  `position`    INT          NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED     NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_column_position` (`column_id`, `position`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `fk_bcd_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bcd_column`  FOREIGN KEY (`column_id`)  REFERENCES `board_columns`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bcd_user`    FOREIGN KEY (`created_by`) REFERENCES `users`          (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- ASSIGNEES KARTY (M:N)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_card_assignees` (
  `card_id` VARCHAR(50)  NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`card_id`, `user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_bca_card` FOREIGN KEY (`card_id`) REFERENCES `board_cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bca_user` FOREIGN KEY (`user_id`) REFERENCES `users`       (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- CHECKLISTY KARET
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_card_checklists` (
  `id`         VARCHAR(50)  NOT NULL,
  `card_id`    VARCHAR(50)  NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `position`   INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_card_position` (`card_id`, `position`),
  CONSTRAINT `fk_bcl_card` FOREIGN KEY (`card_id`) REFERENCES `board_cards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `board_checklist_items` (
  `id`           VARCHAR(50)  NOT NULL,
  `checklist_id` VARCHAR(50)  NOT NULL,
  `text`         TEXT         NOT NULL,
  `is_done`      TINYINT(1)   NOT NULL DEFAULT 0,
  `author_id`    INT UNSIGNED     NULL,
  `assignee_id`  INT UNSIGNED     NULL,
  `deadline`     DATE             NULL,
  `position`     INT          NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_by` INT UNSIGNED     NULL,
  `completed_at` TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  KEY `idx_list_position` (`checklist_id`, `position`),
  KEY `idx_assignee` (`assignee_id`),
  CONSTRAINT `fk_bci_list`     FOREIGN KEY (`checklist_id`) REFERENCES `board_card_checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bci_author`   FOREIGN KEY (`author_id`)    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bci_assignee` FOREIGN KEY (`assignee_id`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bci_compby`   FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- PŘÍLOHY (sjednocené pro karty i checklist itemy)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_type` ENUM('card_cover','card_photo','card_attach','item_attach','task_attach') NOT NULL,
  `owner_id`   VARCHAR(50)  NOT NULL,
  `filename`   VARCHAR(255)     NULL,
  `mime_type`  VARCHAR(80)      NULL,
  `size_bytes` INT UNSIGNED     NULL,
  `url`        VARCHAR(500) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_type`, `owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- AKTIVITNÍ LOG DOKONČENÝCH ÚKOLŮ (pole `completed`)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_card_completion_log` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`      INT UNSIGNED NOT NULL,
  `card_id`         VARCHAR(50)      NULL,
  `card_title`      VARCHAR(500)     NULL,
  `col_name`        VARCHAR(200)     NULL,
  `tag`             VARCHAR(100)     NULL,
  `text`            TEXT             NULL,
  `created_by_name` VARCHAR(120)     NULL,
  `created_at`      TIMESTAMP        NULL,
  `completed_by`    VARCHAR(120)     NULL,
  `completed_at`    TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proj_completed` (`project_id`, `completed_at`),
  CONSTRAINT `fk_bccl_proj` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- MEETING BOARD (živá porada)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_meeting_state` (
  `project_id` INT UNSIGNED NOT NULL,
  `title`      VARCHAR(200) NOT NULL DEFAULT 'Týdenní porada',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`),
  CONSTRAINT `fk_bms_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `board_meeting_agenda` (
  `id`         VARCHAR(50)  NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `text`       VARCHAR(300) NOT NULL,
  `position`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_proj_position` (`project_id`, `position`),
  CONSTRAINT `fk_bma_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `board_meeting_groups` (
  `id`        VARCHAR(50)  NOT NULL,
  `agenda_id` VARCHAR(50)  NOT NULL,
  `name`      VARCHAR(200)     NULL,
  `collapsed` TINYINT(1)   NOT NULL DEFAULT 0,
  `position`  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_agenda` (`agenda_id`, `position`),
  CONSTRAINT `fk_bmg_agenda` FOREIGN KEY (`agenda_id`) REFERENCES `board_meeting_agenda` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `board_meeting_tasks` (
  `id`          VARCHAR(50)  NOT NULL,
  `agenda_id`   VARCHAR(50)  NOT NULL,
  `group_id`    VARCHAR(50)      NULL,
  `text`        TEXT         NOT NULL,
  `is_done`     TINYINT(1)   NOT NULL DEFAULT 0,
  `assignee_id` INT UNSIGNED     NULL,
  `deadline`    DATE             NULL,
  `position`    INT          NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agenda` (`agenda_id`, `position`),
  KEY `idx_group` (`group_id`),
  CONSTRAINT `fk_bmt_agenda` FOREIGN KEY (`agenda_id`) REFERENCES `board_meeting_agenda` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bmt_group`  FOREIGN KEY (`group_id`)  REFERENCES `board_meeting_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bmt_user`   FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historické snapshoty porady — zde nemá smysl rozpadat, je to read-only archiv
CREATE TABLE IF NOT EXISTS `board_meeting_versions` (
  `id`           VARCHAR(50)  NOT NULL,
  `project_id`   INT UNSIGNED NOT NULL,
  `meeting_date` DATE         NOT NULL,
  `title`        VARCHAR(200)     NULL,
  `snapshot`     MEDIUMTEXT   NOT NULL,
  `saved_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_auto`      TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_proj_date` (`project_id`, `meeting_date`),
  CONSTRAINT `fk_bmv_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- FÁZE PROJEKTU
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_phases` (
  `id`         VARCHAR(50)  NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `pct`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `color`      VARCHAR(20)      NULL,
  `start_date` DATE             NULL,
  `end_date`   DATE             NULL,
  `position`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_proj_position` (`project_id`, `position`),
  CONSTRAINT `fk_bph_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- HARMONOGRAM SLUŽEB
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_schedule` (
  `project_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE         NOT NULL,
  `member_id`  INT UNSIGNED NOT NULL,
  `code`       VARCHAR(10)  NOT NULL,
  PRIMARY KEY (`project_id`, `entry_date`, `member_id`),
  KEY `idx_member` (`member_id`),
  CONSTRAINT `fk_bsc_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────
-- LEGENDA SLUŽEB (S, D, N, …)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `board_vac_legend` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `code`       VARCHAR(10)  NOT NULL,
  `label`      VARCHAR(50)  NOT NULL,
  `color`      VARCHAR(20)      NULL,
  `position`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proj_code` (`project_id`, `code`),
  CONSTRAINT `fk_bvl_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
