<?php
// One-time migration: JSON state → normalized tables
//
// Bezpečné:
//  - žádná stará data nemaže ani nemění
//  - obaleno transakcí; při chybě se vše rolluje zpět
//  - idempotentní: už migrované projekty přeskočí, pokud nepřidáš &force=YES
//
// Použití:
//   1) DRY RUN (jen výpis, NIC se nezapíše):
//      https://board.besix.cz/api/migrate_normalize.php
//
//   2) OSTRÝ BĚH (zapíše do nových tabulek):
//      https://board.besix.cz/api/migrate_normalize.php?go=1&confirm=YES
//
//   3) FORCE (přepíše i už migrované projekty):
//      https://board.besix.cz/api/migrate_normalize.php?go=1&confirm=YES&force=YES

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Override JSON header — chceme plain text
while (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit("Není přihlášen. Otevři si dashboard a přihlas se, pak zkus znovu.\n");
}

$dryRun = !(isset($_GET['go']) && ($_GET['confirm'] ?? '') === 'YES');
$force  = ($_GET['force'] ?? '') === 'YES';

echo "==============================================\n";
echo " MIGRACE: JSON state → normalizované tabulky\n";
echo "==============================================\n\n";
echo "Režim:         " . ($dryRun ? "DRY RUN (nic nezapíše)" : "OSTRÝ BĚH (zapisuje)") . "\n";
echo "Re-migrace:    " . ($force ? "ANO (přepíše už migrované)" : "NE (přeskočí už migrované)") . "\n";
echo "Přihlášený:    user_id " . $_SESSION['user_id'] . "\n";
echo "Čas:           " . date('Y-m-d H:i:s') . "\n\n";

$db = getDB();

// Cílový adresář pro extrahované přílohy
$assetsDir = realpath(__DIR__ . '/..') . '/assets/board_attachments';
if (!is_dir($assetsDir)) {
    if (!$dryRun) {
        if (!@mkdir($assetsDir, 0755, true)) {
            exit("CHYBA: nemůžu vytvořit $assetsDir\n");
        }
    }
}
echo "Cíl pro přílohy: $assetsDir\n";
if ($dryRun && !is_dir($assetsDir)) echo "  (v dry-runu se nevytváří)\n";
echo "\n";

// ─── Pomocné funkce ─────────────────────────────────────────

function extractDataUrl(?string $dataUrl, string $destDir, string $prefix, bool $dryRun): ?array {
    if (!is_string($dataUrl) || !preg_match('#^data:([^;]+);base64,(.+)$#s', $dataUrl, $m)) {
        return null;
    }
    $mime = strtolower($m[1]);
    $b64  = $m[2];
    $extMap = [
        'image/jpeg'      => 'jpg', 'image/jpg' => 'jpg',
        'image/png'       => 'png', 'image/gif' => 'gif',
        'image/webp'      => 'webp', 'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];
    $ext = $extMap[$mime] ?? 'bin';
    $filename = $prefix . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if ($dryRun) {
        $estSize = (int)(strlen($b64) * 0.75);
        return [
            'filename' => $filename,
            'mime'     => $mime,
            'size'     => $estSize,
            'url'      => '/assets/board_attachments/' . $filename,
        ];
    }

    $data = base64_decode($b64, true);
    if ($data === false) return null;
    $path = $destDir . '/' . $filename;
    if (file_put_contents($path, $data) === false) return null;

    return [
        'filename' => $filename,
        'mime'     => $mime,
        'size'     => strlen($data),
        'url'      => '/assets/board_attachments/' . $filename,
    ];
}

// "2026-03-31"  → "2026-03-31"
// "2026-04-20T05:19:08.220Z" → "2026-04-20 05:19:08"
// ""            → null
function toMysqlTs($s): ?string {
    if (!is_string($s) || $s === '') return null;
    try {
        $dt = new DateTime($s);
        return $dt->format('Y-m-d H:i:s');
    } catch (\Throwable $e) {
        return null;
    }
}

// Pro DATE sloupce (jen YYYY-MM-DD)
function toMysqlDate($s): ?string {
    if (!is_string($s) || $s === '') return null;
    try {
        $dt = new DateTime($s);
        return $dt->format('Y-m-d');
    } catch (\Throwable $e) {
        return null;
    }
}

// Pro entries v "completed": "30. 3. 2026 10:03" → "2026-03-30 10:03:00"
function czDateToMysql($s): ?string {
    if (!is_string($s) || $s === '') return null;
    if (preg_match('#^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\s+(\d{1,2}):(\d{2})#', $s, $m)) {
        return sprintf('%04d-%02d-%02d %02d:%02d:00',
            (int)$m[3], (int)$m[2], (int)$m[1], (int)$m[4], (int)$m[5]);
    }
    return null;
}

function intOrNull($v): ?int {
    if ($v === null || $v === '' || $v === '?' || !is_numeric($v)) return null;
    return (int)$v;
}

// ─── Zjisti, jestli existují vstupní tabulky ─────────────────
$tables = array_flip($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
if (!isset($tables['board_project_kanban_state'])) {
    exit("CHYBA: neexistuje tabulka board_project_kanban_state. Nejdřív pusť db/migrate_board_prefix.sql.\n");
}
if (!isset($tables['board_columns']) || !isset($tables['board_cards'])) {
    exit("CHYBA: neexistují cílové tabulky (board_columns, board_cards, …). Nejdřív pusť db/migrate_normalize_schema.sql v phpMyAdminu.\n");
}

// ─── Najdi projekty ──────────────────────────────────────────
$projects = $db->query("
    SELECT p.id, p.name
    FROM board_projects p
    JOIN board_project_kanban_state k ON k.project_id = p.id
    ORDER BY p.id
")->fetchAll();

if (empty($projects)) {
    exit("Žádné projekty s kanban-state. Není co migrovat.\n");
}

echo "Najito projektů: " . count($projects) . "\n";
foreach ($projects as $p) echo "  - #" . $p['id'] . " " . $p['name'] . "\n";
echo "\n";

// ─── Hlavní smyčka ───────────────────────────────────────────
if (!$dryRun) $db->beginTransaction();

$totals = ['columns'=>0, 'cards'=>0, 'checklists'=>0, 'items'=>0, 'attachments'=>0,
           'completed'=>0, 'phases'=>0, 'schedule'=>0, 'meeting_agenda'=>0,
           'meeting_groups'=>0, 'meeting_tasks'=>0, 'meeting_versions'=>0, 'vac_legend'=>0];

try {
    foreach ($projects as $p) {
        $pid = (int)$p['id'];
        echo "──── Projekt #$pid: " . $p['name'] . " ────\n";

        $existingCards = (int)$db->query("SELECT COUNT(*) FROM board_cards WHERE project_id=$pid")->fetchColumn();
        if ($existingCards > 0 && !$force) {
            echo "  PŘESKOČENO: už migrováno ($existingCards karet). Re-migraci spustíš s &force=YES.\n\n";
            continue;
        }

        if ($existingCards > 0 && $force && !$dryRun) {
            echo "  Force: mazu staré řádky tohoto projektu z normalizovaných tabulek…\n";
            // Pořadí kvůli FK
            $stmt = $db->prepare("SELECT id FROM board_cards WHERE project_id = ?");
            $stmt->execute([$pid]);
            $cardIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($cardIds) {
                $ph = implode(',', array_fill(0, count($cardIds), '?'));
                $stmt = $db->prepare("SELECT id FROM board_card_checklists WHERE card_id IN ($ph)");
                $stmt->execute($cardIds);
                $clIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if ($clIds) {
                    $ph2 = implode(',', array_fill(0, count($clIds), '?'));
                    $stmt = $db->prepare("SELECT id FROM board_checklist_items WHERE checklist_id IN ($ph2)");
                    $stmt->execute($clIds);
                    $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if ($itemIds) {
                        $ph3 = implode(',', array_fill(0, count($itemIds), '?'));
                        $db->prepare("DELETE FROM board_attachments WHERE owner_type='item_attach' AND owner_id IN ($ph3)")->execute($itemIds);
                    }
                }
                $db->prepare("DELETE FROM board_attachments WHERE owner_type IN ('card_cover','card_photo','card_attach') AND owner_id IN ($ph)")->execute($cardIds);
            }
            $db->prepare("DELETE FROM board_card_completion_log WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_meeting_versions    WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_meeting_agenda      WHERE project_id = ?")->execute([$pid]); // CASCADE smaže groups + tasks
            $db->prepare("DELETE FROM board_meeting_state       WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_phases              WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_schedule            WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_vac_legend          WHERE project_id = ?")->execute([$pid]);
            $db->prepare("DELETE FROM board_cards               WHERE project_id = ?")->execute([$pid]); // CASCADE smaže assignees + checklists + items
            $db->prepare("DELETE FROM board_columns             WHERE project_id = ?")->execute([$pid]);
        }

        // Načti kanban state
        $row = $db->prepare("SELECT state_json FROM board_project_kanban_state WHERE project_id = ?");
        $row->execute([$pid]);
        $stateJson = $row->fetchColumn();
        $state = is_string($stateJson) ? json_decode($stateJson, true) : null;
        if (!is_array($state)) {
            echo "  CHYBA: state_json se nedá parsovat, přeskakuji.\n\n";
            continue;
        }

        $c = ['columns'=>0, 'cards'=>0, 'checklists'=>0, 'items'=>0, 'attachments'=>0,
              'completed'=>0, 'phases'=>0, 'schedule'=>0, 'meeting_agenda'=>0,
              'meeting_groups'=>0, 'meeting_tasks'=>0, 'meeting_versions'=>0, 'vac_legend'=>0];

        // Validní user_id z DB, abychom nepouštěli FK
        $validUsers = array_flip($db->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN));
        $validUser = function ($v) use ($validUsers) {
            $i = intOrNull($v);
            return ($i !== null && isset($validUsers[$i])) ? $i : null;
        };

        // ─── Sloupce ───
        foreach ($state['columns'] ?? [] as $i => $col) {
            if (!is_array($col) || empty($col['id'])) continue;
            $c['columns']++;
            if (!$dryRun) {
                $db->prepare("INSERT INTO board_columns (id, project_id, title, color, archived, position)
                              VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([
                       $col['id'], $pid, (string)($col['title'] ?? ''),
                       $col['color'] ?? null,
                       !empty($col['archived']) ? 1 : 0,
                       $i,
                   ]);
            }
        }

        // ─── Karty ───
        foreach ($state['cards'] ?? [] as $i => $card) {
            if (!is_array($card) || empty($card['id'])) continue;
            $c['cards']++;

            // Cover
            $coverUrl = null;
            if (!empty($card['cover'])) {
                $att = extractDataUrl($card['cover'], $assetsDir, 'card_' . $card['id'], $dryRun);
                if ($att) {
                    $coverUrl = $att['url'];
                    $c['attachments']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_attachments (owner_type, owner_id, filename, mime_type, size_bytes, url)
                                      VALUES ('card_cover', ?, ?, ?, ?, ?)")
                           ->execute([$card['id'], $att['filename'], $att['mime'], $att['size'], $att['url']]);
                    }
                } else if (is_string($card['cover']) && !str_starts_with($card['cover'], 'data:')) {
                    // už je to URL, nech jak je
                    $coverUrl = $card['cover'];
                }
            }

            if (!$dryRun) {
                $priority = $card['priority'] ?? 'low';
                if (!in_array($priority, ['low','med','high'], true)) $priority = 'low';
                $db->prepare("INSERT INTO board_cards
                              (id, project_id, column_id, title, description, priority, deadline, tag, cover_url, position, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([
                       $card['id'], $pid, (string)($card['colId'] ?? ''),
                       (string)($card['title'] ?? ''), $card['desc'] ?? null,
                       $priority,
                       toMysqlDate($card['deadline'] ?? null),
                       $card['tag'] ?? null,
                       $coverUrl,
                       $i,
                       $validUser($card['createdBy'] ?? null),
                       toMysqlTs($card['createdAt'] ?? null) ?: date('Y-m-d H:i:s'),
                   ]);
            }

            // Assignees
            foreach ($card['assignees'] ?? [] as $uid) {
                $vu = $validUser($uid);
                if ($vu !== null && !$dryRun) {
                    $db->prepare("INSERT IGNORE INTO board_card_assignees (card_id, user_id) VALUES (?, ?)")
                       ->execute([$card['id'], $vu]);
                }
            }

            // Photos
            foreach ($card['photos'] ?? [] as $j => $photo) {
                if (!is_string($photo)) continue;
                $att = extractDataUrl($photo, $assetsDir, 'photo_' . $card['id'] . '_' . $j, $dryRun);
                if ($att) {
                    $c['attachments']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_attachments (owner_type, owner_id, filename, mime_type, size_bytes, url)
                                      VALUES ('card_photo', ?, ?, ?, ?, ?)")
                           ->execute([$card['id'], $att['filename'], $att['mime'], $att['size'], $att['url']]);
                    }
                }
            }

            // Attachments (karta)
            foreach ($card['attachments'] ?? [] as $j => $att) {
                if (!is_array($att)) continue;
                $dataUrl = $att['data'] ?? null;
                $ext = extractDataUrl($dataUrl, $assetsDir, 'attach_' . $card['id'] . '_' . $j, $dryRun);
                if ($ext) {
                    $c['attachments']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_attachments (owner_type, owner_id, filename, mime_type, size_bytes, url)
                                      VALUES ('card_attach', ?, ?, ?, ?, ?)")
                           ->execute([
                               $card['id'],
                               (string)($att['name'] ?? $ext['filename']),
                               (string)($att['type'] ?? $ext['mime']),
                               (int)($att['size'] ?? $ext['size']),
                               $ext['url'],
                           ]);
                    }
                }
            }

            // Checklisty
            foreach ($card['checklists'] ?? [] as $k => $cl) {
                if (!is_array($cl) || empty($cl['id'])) continue;
                $c['checklists']++;
                if (!$dryRun) {
                    $db->prepare("INSERT INTO board_card_checklists (id, card_id, name, position)
                                  VALUES (?, ?, ?, ?)")
                       ->execute([$cl['id'], $card['id'], (string)($cl['name'] ?? ''), $k]);
                }

                foreach ($cl['items'] ?? [] as $m => $item) {
                    if (!is_array($item) || empty($item['id'])) continue;
                    $c['items']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_checklist_items
                                      (id, checklist_id, text, is_done, author_id, assignee_id, deadline, position, created_at, completed_by, completed_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                           ->execute([
                               $item['id'], $cl['id'], (string)($item['text'] ?? ''),
                               !empty($item['done']) ? 1 : 0,
                               $validUser($item['author']   ?? null),
                               $validUser($item['assignee'] ?? null),
                               toMysqlDate($item['deadline'] ?? null),
                               $m,
                               toMysqlTs($item['created'] ?? null) ?: date('Y-m-d H:i:s'),
                               $validUser($item['completedBy'] ?? null),
                               toMysqlTs($item['completedAt'] ?? null),
                           ]);
                    }

                    // Item attachments
                    foreach ($item['attachments'] ?? [] as $n => $att) {
                        if (!is_array($att)) continue;
                        $dataUrl = $att['data'] ?? null;
                        $ext = extractDataUrl($dataUrl, $assetsDir, 'item_' . $item['id'] . '_' . $n, $dryRun);
                        if ($ext) {
                            $c['attachments']++;
                            if (!$dryRun) {
                                $db->prepare("INSERT INTO board_attachments (owner_type, owner_id, filename, mime_type, size_bytes, url)
                                              VALUES ('item_attach', ?, ?, ?, ?, ?)")
                                   ->execute([
                                       $item['id'],
                                       (string)($att['name'] ?? $ext['filename']),
                                       (string)($att['type'] ?? $ext['mime']),
                                       (int)($att['size'] ?? $ext['size']),
                                       $ext['url'],
                                   ]);
                            }
                        }
                    }
                }
            }
        }

        // ─── Completed log ───
        foreach ($state['completed'] ?? [] as $cl) {
            if (!is_array($cl)) continue;
            $c['completed']++;
            if (!$dryRun) {
                $db->prepare("INSERT INTO board_card_completion_log
                              (project_id, card_title, col_name, tag, text, created_by_name, created_at, completed_by, completed_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([
                       $pid,
                       $cl['cardTitle']  ?? null,
                       $cl['colName']    ?? null,
                       $cl['tag']        ?? null,
                       $cl['text']       ?? null,
                       $cl['createdBy']  ?? null,
                       czDateToMysql($cl['createdAt'] ?? null),
                       $cl['completedBy'] ?? null,
                       czDateToMysql($cl['completedAt'] ?? null),
                   ]);
            }
        }

        // ─── Fáze ───
        foreach ($state['phases'] ?? [] as $i => $ph) {
            if (!is_array($ph) || empty($ph['id'])) continue;
            $c['phases']++;
            if (!$dryRun) {
                $db->prepare("INSERT INTO board_phases (id, project_id, name, pct, color, start_date, end_date, position)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE name=VALUES(name), pct=VALUES(pct), color=VALUES(color),
                                                      start_date=VALUES(start_date), end_date=VALUES(end_date), position=VALUES(position)")
                   ->execute([
                       $ph['id'], $pid, (string)($ph['name'] ?? ''),
                       max(0, min(100, (int)($ph['pct'] ?? 0))),
                       $ph['color'] ?? null,
                       toMysqlDate($ph['startDate'] ?? null),
                       toMysqlDate($ph['endDate']   ?? null),
                       $i,
                   ]);
            }
        }

        // ─── Schedule ───
        foreach ($state['schedule'] ?? [] as $date => $entries) {
            if (!is_array($entries) || empty($entries)) continue; // {} nebo [] = nic
            $mysqlDate = toMysqlDate((string)$date);
            if (!$mysqlDate) continue;
            foreach ($entries as $memberId => $code) {
                if (!is_string($code) || $code === '') continue;
                $mid = intOrNull($memberId);
                if ($mid === null) continue;
                $c['schedule']++;
                if (!$dryRun) {
                    $db->prepare("INSERT INTO board_schedule (project_id, entry_date, member_id, code)
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE code=VALUES(code)")
                       ->execute([$pid, $mysqlDate, $mid, $code]);
                }
            }
        }

        // ─── Meeting state (z board_project_meeting_state) ───
        $mrow = $db->prepare("SELECT living_meeting, meeting_versions, phases, schedule, vac_legend
                              FROM board_project_meeting_state WHERE project_id = ?");
        $mrow->execute([$pid]);
        $mstate = $mrow->fetch();

        if ($mstate) {
            $lm = json_decode($mstate['living_meeting'] ?? '{}', true) ?: [];

            if (!$dryRun) {
                $db->prepare("INSERT INTO board_meeting_state (project_id, title)
                              VALUES (?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title)")
                   ->execute([$pid, (string)($lm['title'] ?? 'Týdenní porada')]);
            }

            foreach ($lm['agenda'] ?? [] as $i => $ag) {
                if (!is_array($ag) || empty($ag['id'])) continue;
                $c['meeting_agenda']++;
                if (!$dryRun) {
                    $db->prepare("INSERT INTO board_meeting_agenda (id, project_id, text, position)
                                  VALUES (?, ?, ?, ?)")
                       ->execute([$ag['id'], $pid, (string)($ag['text'] ?? ''), $i]);
                }

                foreach ($ag['groups'] ?? [] as $j => $g) {
                    if (!is_array($g) || empty($g['id'])) continue;
                    $c['meeting_groups']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_meeting_groups (id, agenda_id, name, collapsed, position)
                                      VALUES (?, ?, ?, ?, ?)")
                           ->execute([
                               $g['id'], $ag['id'],
                               (string)($g['name'] ?? ''),
                               !empty($g['collapsed']) ? 1 : 0,
                               $j,
                           ]);
                    }
                }

                foreach ($ag['tasks'] ?? [] as $j => $t) {
                    if (!is_array($t) || empty($t['id'])) continue;
                    $c['meeting_tasks']++;
                    if (!$dryRun) {
                        $db->prepare("INSERT INTO board_meeting_tasks
                                      (id, agenda_id, group_id, text, is_done, assignee_id, deadline, position, created_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                           ->execute([
                               $t['id'], $ag['id'],
                               !empty($t['groupId']) ? $t['groupId'] : null,
                               (string)($t['text'] ?? ''),
                               !empty($t['done']) ? 1 : 0,
                               $validUser($t['assignee'] ?? null),
                               toMysqlDate($t['deadline'] ?? null),
                               $j,
                               toMysqlTs($t['createdAt'] ?? null) ?: date('Y-m-d H:i:s'),
                           ]);
                    }
                }
            }

            // Historické verze porady — celý JSON snapshot
            $versions = json_decode($mstate['meeting_versions'] ?? '[]', true) ?: [];
            foreach ($versions as $v) {
                if (!is_array($v) || empty($v['id'])) continue;
                $c['meeting_versions']++;
                if (!$dryRun) {
                    $db->prepare("INSERT INTO board_meeting_versions
                                  (id, project_id, meeting_date, title, snapshot, saved_at, is_auto)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE snapshot=VALUES(snapshot), saved_at=VALUES(saved_at)")
                       ->execute([
                           $v['id'], $pid,
                           toMysqlDate($v['date'] ?? null) ?: date('Y-m-d'),
                           $v['title'] ?? null,
                           json_encode($v, JSON_UNESCAPED_UNICODE),
                           toMysqlTs($v['savedAt'] ?? null) ?: date('Y-m-d H:i:s'),
                           !empty($v['auto']) ? 1 : 0,
                       ]);
                }
            }

            // Vac legend
            $vac = json_decode($mstate['vac_legend'] ?? '[]', true) ?: [];
            foreach ($vac as $i => $v) {
                if (!is_array($v) || empty($v['key'])) continue;
                $c['vac_legend']++;
                if (!$dryRun) {
                    $db->prepare("INSERT INTO board_vac_legend (project_id, code, label, color, position)
                                  VALUES (?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE label=VALUES(label), color=VALUES(color), position=VALUES(position)")
                       ->execute([
                           $pid, (string)$v['key'],
                           (string)($v['label'] ?? ''),
                           $v['color'] ?? null,
                           $i,
                       ]);
                }
            }

            // schedule a phases v meeting_state jsou stejné jako v kanban-state, neduplikujeme
        }

        // Výpis
        foreach ($c as $k => $v) $totals[$k] += $v;
        echo "  sloupce={$c['columns']}  karty={$c['cards']}  checklisty={$c['checklists']}  položky={$c['items']}\n";
        echo "  přílohy={$c['attachments']}  dokončené={$c['completed']}  fáze={$c['phases']}  schedule={$c['schedule']}\n";
        echo "  meeting_agenda={$c['meeting_agenda']}  meeting_groups={$c['meeting_groups']}  meeting_tasks={$c['meeting_tasks']}\n";
        echo "  meeting_versions={$c['meeting_versions']}  vac_legend={$c['vac_legend']}\n\n";
    }

    if (!$dryRun) {
        $db->commit();
        echo "════════════════════════════════════════════\n";
        echo " HOTOVO. Data zapsána do nových tabulek.\n";
        echo "════════════════════════════════════════════\n";
        echo "Stará JSON data v board_project_kanban_state a board_project_meeting_state ZŮSTALA NETKNUTÁ.\n";
        echo "Aplikace dál běží proti starým JSON tabulkám. Až ověříš, že nová data sedí, můžu přepsat backend.\n\n";
    } else {
        echo "════════════════════════════════════════════\n";
        echo " DRY RUN HOTOV. NIC se nezapsalo.\n";
        echo "════════════════════════════════════════════\n";
        echo "Až budeš s výsledky spokojený, pusť ostře:\n";
        echo "  https://board.besix.cz/api/migrate_normalize.php?go=1&confirm=YES\n\n";
    }

    echo "Celkové součty napříč projekty:\n";
    foreach ($totals as $k => $v) echo "  $k = $v\n";

} catch (\Throwable $e) {
    if (!$dryRun && $db->inTransaction()) $db->rollBack();
    echo "\n!!! CHYBA !!!\n";
    echo "Zpráva: " . $e->getMessage() . "\n";
    echo "Soubor: " . $e->getFile() . " řádek " . $e->getLine() . "\n";
    echo "Transakce odvolána. NIC se nezapsalo.\n";
    if ($e instanceof PDOException) {
        echo "SQLSTATE: " . $e->getCode() . "\n";
    }
}
