<?php
// Phase 1 dual-write: po legacy save zápisu do board_project_kanban_state /
// board_project_meeting_state materializujeme stejná data i do normalizovaných
// tabulek. Legacy JSON je dál source of truth — selhání zde se loguje, ale
// nepropaguje (nesmí rozbít save z UI).
//
// Schéma se bootstrapuje líně při prvním volání (idempotentní CREATE TABLE).

function nzEnsureSchema(PDO $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $db->exec("CREATE TABLE IF NOT EXISTS `board_columns` (
        `id`         VARCHAR(50)  NOT NULL,
        `project_id` INT UNSIGNED NOT NULL,
        `title`      VARCHAR(200) NOT NULL,
        `color`      VARCHAR(20)      NULL,
        `archived`   TINYINT(1)   NOT NULL DEFAULT 0,
        `position`   INT          NOT NULL DEFAULT 0,
        `extra_json` MEDIUMTEXT       NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project_position` (`project_id`, `position`),
        CONSTRAINT `fk_bc_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_cards` (
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
        `extra_json`  MEDIUMTEXT       NULL,
        `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_column_position` (`column_id`, `position`),
        KEY `idx_project` (`project_id`),
        CONSTRAINT `fk_bcd_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_bcd_column`  FOREIGN KEY (`column_id`)  REFERENCES `board_columns`  (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_bcd_user`    FOREIGN KEY (`created_by`) REFERENCES `users`          (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_card_assignees` (
        `card_id` VARCHAR(50)  NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        PRIMARY KEY (`card_id`, `user_id`),
        KEY `idx_user` (`user_id`),
        CONSTRAINT `fk_bca_card` FOREIGN KEY (`card_id`) REFERENCES `board_cards` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_bca_user` FOREIGN KEY (`user_id`) REFERENCES `users`       (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_card_checklists` (
        `id`         VARCHAR(50)  NOT NULL,
        `card_id`    VARCHAR(50)  NOT NULL,
        `name`       VARCHAR(200) NOT NULL,
        `position`   INT          NOT NULL DEFAULT 0,
        `extra_json` MEDIUMTEXT       NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_card_position` (`card_id`, `position`),
        CONSTRAINT `fk_bcl_card` FOREIGN KEY (`card_id`) REFERENCES `board_cards` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_checklist_items` (
        `id`           VARCHAR(50)  NOT NULL,
        `checklist_id` VARCHAR(50)  NOT NULL,
        `text`         TEXT         NOT NULL,
        `is_done`      TINYINT(1)   NOT NULL DEFAULT 0,
        `author_id`    INT UNSIGNED     NULL,
        `assignee_id`  INT UNSIGNED     NULL,
        `deadline`     DATE             NULL,
        `position`     INT          NOT NULL DEFAULT 0,
        `extra_json`   MEDIUMTEXT       NULL,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_attachments` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_card_completion_log` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_meeting_state` (
        `project_id` INT UNSIGNED NOT NULL,
        `title`      VARCHAR(200) NOT NULL DEFAULT 'Týdenní porada',
        `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`project_id`),
        CONSTRAINT `fk_bms_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_meeting_agenda` (
        `id`         VARCHAR(50)  NOT NULL,
        `project_id` INT UNSIGNED NOT NULL,
        `text`       VARCHAR(300) NOT NULL,
        `position`   INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_proj_position` (`project_id`, `position`),
        CONSTRAINT `fk_bma_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_meeting_groups` (
        `id`        VARCHAR(50)  NOT NULL,
        `agenda_id` VARCHAR(50)  NOT NULL,
        `name`      VARCHAR(200)     NULL,
        `collapsed` TINYINT(1)   NOT NULL DEFAULT 0,
        `position`  INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `idx_agenda` (`agenda_id`, `position`),
        CONSTRAINT `fk_bmg_agenda` FOREIGN KEY (`agenda_id`) REFERENCES `board_meeting_agenda` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_meeting_tasks` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_meeting_versions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_phases` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_schedule` (
        `project_id` INT UNSIGNED NOT NULL,
        `entry_date` DATE         NOT NULL,
        `member_id`  INT UNSIGNED NOT NULL,
        `code`       VARCHAR(10)  NOT NULL,
        PRIMARY KEY (`project_id`, `entry_date`, `member_id`),
        KEY `idx_member` (`member_id`),
        CONSTRAINT `fk_bsc_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `board_vac_legend` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `code`       VARCHAR(10)  NOT NULL,
        `label`      VARCHAR(50)  NOT NULL,
        `color`      VARCHAR(20)      NULL,
        `position`   INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_proj_code` (`project_id`, `code`),
        CONSTRAINT `fk_bvl_project` FOREIGN KEY (`project_id`) REFERENCES `board_projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pokud tabulky existují ze starší verze schématu bez extra_json, doplníme sloupec
    foreach (['board_columns','board_cards','board_card_checklists','board_checklist_items'] as $t) {
        try { $db->exec("ALTER TABLE `$t` ADD COLUMN `extra_json` MEDIUMTEXT NULL"); } catch (\Throwable $e) {}
    }
}

function nzToDate($s): ?string {
    if (!is_string($s) || $s === '') return null;
    try { return (new DateTime($s))->format('Y-m-d'); } catch (\Throwable $e) { return null; }
}

function nzToTs($s): ?string {
    if (!is_string($s) || $s === '') return null;
    try { return (new DateTime($s))->format('Y-m-d H:i:s'); } catch (\Throwable $e) { return null; }
}

// "30. 3. 2026 10:03" → "2026-03-30 10:03:00"
function nzCzToTs($s): ?string {
    if (!is_string($s) || $s === '') return null;
    if (preg_match('#^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\s+(\d{1,2}):(\d{2})#', $s, $m)) {
        return sprintf('%04d-%02d-%02d %02d:%02d:00',
            (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5]);
    }
    return null;
}

function nzIntOrNull($v): ?int {
    if ($v === null || $v === '' || $v === '?' || !is_numeric($v)) return null;
    return (int)$v;
}

function nzValidUserFactory(PDO $db): callable {
    $valid = array_flip($db->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN));
    return function ($v) use ($valid) {
        $i = nzIntOrNull($v);
        return ($i !== null && isset($valid[$i])) ? $i : null;
    };
}

// Vrátí JSON s poli, která NEJSOU v $known. Null pokud nic navíc není.
function nzExtra(array $src, array $known): ?string {
    $extra = array_diff_key($src, array_flip($known));
    if (empty($extra)) return null;
    return json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

function dualWriteKanban(PDO $db, int $projectId, array $state): void {
    try {
        nzEnsureSchema($db);
        $validUser = nzValidUserFactory($db);

        $db->beginTransaction();

        // 1) Smaž existující data tohoto projektu (cascade vyřeší většinu)
        $cardIds = $db->prepare("SELECT id FROM board_cards WHERE project_id = ?");
        $cardIds->execute([$projectId]);
        $cardIds = $cardIds->fetchAll(PDO::FETCH_COLUMN);

        $itemIds = [];
        if ($cardIds) {
            $ph = implode(',', array_fill(0, count($cardIds), '?'));
            $stmt = $db->prepare("SELECT i.id FROM board_checklist_items i
                                  JOIN board_card_checklists cl ON cl.id = i.checklist_id
                                  WHERE cl.card_id IN ($ph)");
            $stmt->execute($cardIds);
            $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        // board_attachments nemá FK → musíme uklidit ručně
        $ownerIds = array_merge($cardIds, $itemIds);
        if ($ownerIds) {
            $ph = implode(',', array_fill(0, count($ownerIds), '?'));
            $db->prepare("DELETE FROM board_attachments
                          WHERE owner_type IN ('card_cover','card_photo','card_attach','item_attach')
                            AND owner_id IN ($ph)")->execute($ownerIds);
        }
        $db->prepare("DELETE FROM board_card_completion_log WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_cards   WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_columns WHERE project_id = ?")->execute([$projectId]);

        // 2) Sloupce
        $colKnown = ['id','title','color','archived'];
        $colStmt = $db->prepare("INSERT INTO board_columns
                                 (id, project_id, title, color, archived, position, extra_json)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach (($state['columns'] ?? []) as $i => $col) {
            if (!is_array($col) || empty($col['id'])) continue;
            $colStmt->execute([
                $col['id'], $projectId,
                (string)($col['title'] ?? ''),
                $col['color'] ?? null,
                !empty($col['archived']) ? 1 : 0,
                $i,
                nzExtra($col, $colKnown),
            ]);
        }

        // 3) Karty + assignees + checklisty + položky + přílohy
        $cardKnown = ['id','colId','title','desc','priority','deadline','tag','cover','createdBy','createdAt',
                      'assignees','photos','attachments','checklists'];
        $clKnown   = ['id','name','items'];
        $itemKnown = ['id','text','done','author','assignee','deadline','created','completedBy','completedAt','attachments'];

        $cardStmt = $db->prepare("INSERT INTO board_cards
            (id, project_id, column_id, title, description, priority, deadline, tag, cover_url, position, created_by, extra_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $assStmt  = $db->prepare("INSERT IGNORE INTO board_card_assignees (card_id, user_id) VALUES (?, ?)");
        $clStmt   = $db->prepare("INSERT INTO board_card_checklists
            (id, card_id, name, position, extra_json) VALUES (?, ?, ?, ?, ?)");
        $itemStmt = $db->prepare("INSERT INTO board_checklist_items
            (id, checklist_id, text, is_done, author_id, assignee_id, deadline, position, extra_json, created_at, completed_by, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $attStmt  = $db->prepare("INSERT INTO board_attachments
            (owner_type, owner_id, filename, mime_type, size_bytes, url) VALUES (?, ?, ?, ?, ?, ?)");

        foreach (($state['cards'] ?? []) as $i => $card) {
            if (!is_array($card) || empty($card['id'])) continue;

            $priority = $card['priority'] ?? 'low';
            if (!in_array($priority, ['low','med','high'], true)) $priority = 'low';

            // Cover: data: URL necháme jen jako placeholder URL — full extrakci dělá až migrate_normalize.php
            $cover = $card['cover'] ?? null;
            $coverUrl = (is_string($cover) && !str_starts_with($cover, 'data:')) ? $cover : null;

            $cardStmt->execute([
                $card['id'], $projectId,
                (string)($card['colId'] ?? ''),
                (string)($card['title'] ?? ''),
                $card['desc'] ?? null,
                $priority,
                nzToDate($card['deadline'] ?? null),
                $card['tag'] ?? null,
                $coverUrl,
                $i,
                $validUser($card['createdBy'] ?? null),
                nzExtra($card, $cardKnown),
                nzToTs($card['createdAt'] ?? null) ?: date('Y-m-d H:i:s'),
            ]);

            // Assignees
            foreach (($card['assignees'] ?? []) as $uid) {
                $vu = $validUser($uid);
                if ($vu !== null) $assStmt->execute([$card['id'], $vu]);
            }

            // Cover jako příloha (jen pokud máme reálnou URL, ne data:URL placeholder)
            if ($coverUrl) {
                $attStmt->execute(['card_cover', $card['id'], null, null, null, $coverUrl]);
            }

            // Photos — jen ty, co už jsou URL (data: URL přeskočíme)
            foreach (($card['photos'] ?? []) as $photo) {
                if (is_string($photo) && !str_starts_with($photo, 'data:')) {
                    $attStmt->execute(['card_photo', $card['id'], null, null, null, $photo]);
                }
            }

            // Card attachments
            foreach (($card['attachments'] ?? []) as $att) {
                if (!is_array($att)) continue;
                $url = $att['url'] ?? $att['data'] ?? null;
                if (!is_string($url) || str_starts_with($url, 'data:')) continue;
                $attStmt->execute([
                    'card_attach', $card['id'],
                    $att['name'] ?? null,
                    $att['type'] ?? null,
                    isset($att['size']) ? (int)$att['size'] : null,
                    $url,
                ]);
            }

            // Checklisty + položky
            foreach (($card['checklists'] ?? []) as $k => $cl) {
                if (!is_array($cl) || empty($cl['id'])) continue;
                $clStmt->execute([
                    $cl['id'], $card['id'],
                    (string)($cl['name'] ?? ''),
                    $k,
                    nzExtra($cl, $clKnown),
                ]);

                foreach (($cl['items'] ?? []) as $m => $item) {
                    if (!is_array($item) || empty($item['id'])) continue;
                    $itemStmt->execute([
                        $item['id'], $cl['id'],
                        (string)($item['text'] ?? ''),
                        !empty($item['done']) ? 1 : 0,
                        $validUser($item['author']   ?? null),
                        $validUser($item['assignee'] ?? null),
                        nzToDate($item['deadline'] ?? null),
                        $m,
                        nzExtra($item, $itemKnown),
                        nzToTs($item['created'] ?? null) ?: date('Y-m-d H:i:s'),
                        $validUser($item['completedBy'] ?? null),
                        nzToTs($item['completedAt'] ?? null),
                    ]);

                    // Item attachments
                    foreach (($item['attachments'] ?? []) as $att) {
                        if (!is_array($att)) continue;
                        $url = $att['url'] ?? $att['data'] ?? null;
                        if (!is_string($url) || str_starts_with($url, 'data:')) continue;
                        $attStmt->execute([
                            'item_attach', $item['id'],
                            $att['name'] ?? null,
                            $att['type'] ?? null,
                            isset($att['size']) ? (int)$att['size'] : null,
                            $url,
                        ]);
                    }
                }
            }
        }

        // 4) Completion log (append-only, ale frontend posílá kompletní pole — wipe+insert je OK)
        $logStmt = $db->prepare("INSERT INTO board_card_completion_log
            (project_id, card_title, col_name, tag, text, created_by_name, created_at, completed_by, completed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach (($state['completed'] ?? []) as $cl) {
            if (!is_array($cl)) continue;
            $logStmt->execute([
                $projectId,
                $cl['cardTitle']   ?? null,
                $cl['colName']     ?? null,
                $cl['tag']         ?? null,
                $cl['text']        ?? null,
                $cl['createdBy']   ?? null,
                nzCzToTs($cl['createdAt']   ?? null),
                $cl['completedBy'] ?? null,
                nzCzToTs($cl['completedAt'] ?? null),
            ]);
        }

        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[dual-write kanban] project=' . $projectId . ' ' . $e->getMessage());
    }
}

function dualWriteMeeting(PDO $db, int $projectId, array $body): void {
    try {
        nzEnsureSchema($db);
        $validUser = nzValidUserFactory($db);

        $lm  = is_array($body['livingMeeting']   ?? null) ? $body['livingMeeting']   : [];
        $mvs = is_array($body['meetingVersions'] ?? null) ? $body['meetingVersions'] : [];
        $phs = is_array($body['phases']          ?? null) ? $body['phases']          : [];
        $sch = is_array($body['schedule']        ?? null) ? $body['schedule']        : [];
        $vl  = is_array($body['vacLegend']       ?? null) ? $body['vacLegend']       : [];

        $db->beginTransaction();

        // Wipe (agenda CASCADE smaže groups + tasks)
        $db->prepare("DELETE FROM board_meeting_agenda   WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_meeting_state    WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_meeting_versions WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_phases           WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_schedule         WHERE project_id = ?")->execute([$projectId]);
        $db->prepare("DELETE FROM board_vac_legend       WHERE project_id = ?")->execute([$projectId]);

        // Meeting state (hlavička)
        $db->prepare("INSERT INTO board_meeting_state (project_id, title) VALUES (?, ?)")
           ->execute([$projectId, (string)($lm['title'] ?? 'Týdenní porada')]);

        // Agenda + groups + tasks
        $agStmt   = $db->prepare("INSERT INTO board_meeting_agenda
            (id, project_id, text, position) VALUES (?, ?, ?, ?)");
        $grpStmt  = $db->prepare("INSERT INTO board_meeting_groups
            (id, agenda_id, name, collapsed, position) VALUES (?, ?, ?, ?, ?)");
        $taskStmt = $db->prepare("INSERT INTO board_meeting_tasks
            (id, agenda_id, group_id, text, is_done, assignee_id, deadline, position, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach (($lm['agenda'] ?? []) as $i => $ag) {
            if (!is_array($ag) || empty($ag['id'])) continue;
            $agStmt->execute([$ag['id'], $projectId, (string)($ag['text'] ?? ''), $i]);

            foreach (($ag['groups'] ?? []) as $j => $g) {
                if (!is_array($g) || empty($g['id'])) continue;
                $grpStmt->execute([
                    $g['id'], $ag['id'],
                    (string)($g['name'] ?? ''),
                    !empty($g['collapsed']) ? 1 : 0,
                    $j,
                ]);
            }

            foreach (($ag['tasks'] ?? []) as $j => $t) {
                if (!is_array($t) || empty($t['id'])) continue;
                $taskStmt->execute([
                    $t['id'], $ag['id'],
                    !empty($t['groupId']) ? $t['groupId'] : null,
                    (string)($t['text'] ?? ''),
                    !empty($t['done']) ? 1 : 0,
                    $validUser($t['assignee'] ?? null),
                    nzToDate($t['deadline'] ?? null),
                    $j,
                    nzToTs($t['createdAt'] ?? null) ?: date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Versions
        $verStmt = $db->prepare("INSERT INTO board_meeting_versions
            (id, project_id, meeting_date, title, snapshot, saved_at, is_auto)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($mvs as $v) {
            if (!is_array($v) || empty($v['id'])) continue;
            $verStmt->execute([
                $v['id'], $projectId,
                nzToDate($v['date'] ?? null) ?: date('Y-m-d'),
                $v['title'] ?? null,
                json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                nzToTs($v['savedAt'] ?? null) ?: date('Y-m-d H:i:s'),
                !empty($v['auto']) ? 1 : 0,
            ]);
        }

        // Phases
        $phStmt = $db->prepare("INSERT INTO board_phases
            (id, project_id, name, pct, color, start_date, end_date, position)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($phs as $i => $p) {
            if (!is_array($p) || empty($p['id'])) continue;
            $phStmt->execute([
                $p['id'], $projectId,
                (string)($p['name'] ?? ''),
                max(0, min(100, (int)($p['pct'] ?? 0))),
                $p['color'] ?? null,
                nzToDate($p['startDate'] ?? null),
                nzToDate($p['endDate']   ?? null),
                $i,
            ]);
        }

        // Schedule
        $schStmt = $db->prepare("INSERT INTO board_schedule
            (project_id, entry_date, member_id, code) VALUES (?, ?, ?, ?)");
        foreach ($sch as $date => $entries) {
            if (!is_array($entries) || empty($entries)) continue;
            $mysqlDate = nzToDate((string)$date);
            if (!$mysqlDate) continue;
            foreach ($entries as $memberId => $code) {
                if (!is_string($code) || $code === '') continue;
                $mid = nzIntOrNull($memberId);
                if ($mid === null) continue;
                $schStmt->execute([$projectId, $mysqlDate, $mid, $code]);
            }
        }

        // Vac legend
        $vlStmt = $db->prepare("INSERT INTO board_vac_legend
            (project_id, code, label, color, position) VALUES (?, ?, ?, ?, ?)");
        foreach ($vl as $i => $v) {
            if (!is_array($v) || empty($v['key'])) continue;
            $vlStmt->execute([
                $projectId, (string)$v['key'],
                (string)($v['label'] ?? ''),
                $v['color'] ?? null,
                $i,
            ]);
        }

        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[dual-write meeting] project=' . $projectId . ' ' . $e->getMessage());
    }
}
