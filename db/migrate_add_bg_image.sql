-- Add bg_image column to projects for custom board backgrounds
ALTER TABLE projects ADD COLUMN bg_image VARCHAR(500) NULL DEFAULT NULL;
