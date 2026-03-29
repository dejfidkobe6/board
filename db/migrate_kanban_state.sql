-- Migration: store kanban state (columns + cards) per project on server
-- Run once in phpMyAdmin SQL tab

CREATE TABLE IF NOT EXISTS `project_kanban_state` (
  `project_id`  INT UNSIGNED NOT NULL,
  `state_json`  MEDIUMTEXT   NOT NULL DEFAULT '{}',
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`),
  CONSTRAINT `fk_kanban_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
