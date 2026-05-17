-- Migration: add `board_` prefix to all board-specific tables
-- This preserves all data, indexes and foreign keys (atomic RENAME TABLE).
--
-- Tables that get renamed (board-specific data):
--   apps                       -> board_apps
--   projects                   -> board_projects
--   project_members            -> board_project_members
--   invitations                -> board_invitations
--   project_kanban_state       -> board_project_kanban_state
--   project_kanban_backups     -> board_project_kanban_backups
--   project_meeting_state      -> board_project_meeting_state
--
-- Tables that are NOT renamed (shared between BeSix apps):
--   users               (shared user accounts)
--   php_sessions        (shared PHP sessions / SSO)
--   remember_tokens     (shared remember-me tokens)
--
-- Safe to re-run: skips tables that have already been renamed.

SET foreign_key_checks = 0;

-- Use a stored procedure so we can conditionally rename only what exists.
DELIMITER //
DROP PROCEDURE IF EXISTS board_apply_prefix //
CREATE PROCEDURE board_apply_prefix()
BEGIN
    DECLARE db_name VARCHAR(64);
    SET db_name = DATABASE();

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='apps')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_apps') THEN
        RENAME TABLE `apps` TO `board_apps`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='projects')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_projects') THEN
        RENAME TABLE `projects` TO `board_projects`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='project_members')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_project_members') THEN
        RENAME TABLE `project_members` TO `board_project_members`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='invitations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_invitations') THEN
        RENAME TABLE `invitations` TO `board_invitations`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='project_kanban_state')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_project_kanban_state') THEN
        RENAME TABLE `project_kanban_state` TO `board_project_kanban_state`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='project_kanban_backups')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_project_kanban_backups') THEN
        RENAME TABLE `project_kanban_backups` TO `board_project_kanban_backups`;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='project_meeting_state')
       AND NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=db_name AND table_name='board_project_meeting_state') THEN
        RENAME TABLE `project_meeting_state` TO `board_project_meeting_state`;
    END IF;
END //
DELIMITER ;

CALL board_apply_prefix();
DROP PROCEDURE IF EXISTS board_apply_prefix;

SET foreign_key_checks = 1;
