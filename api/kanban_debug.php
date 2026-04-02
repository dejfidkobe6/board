<?php
// TEMPORARY DEBUG — remove after data recovery
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

requireLogin();
$userId = $_SESSION['user_id'] ?? 0;

$db = getDB();

// Only show projects where this user is owner/admin
$stmt = $db->prepare('
    SELECT k.project_id, k.updated_at, k.state_json, p.name as project_name
    FROM project_kanban_state k
    JOIN projects p ON p.id = k.project_id
    JOIN project_members pm ON pm.project_id = k.project_id AND pm.user_id = ?
    WHERE pm.role IN (\'owner\',\'admin\')
    ORDER BY k.updated_at DESC
');
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $r) {
    $state = json_decode($r['state_json'], true);
    $result[] = [
        'project_id'   => $r['project_id'],
        'project_name' => $r['project_name'],
        'updated_at'   => $r['updated_at'],
        'columns_count'=> count($state['columns'] ?? []),
        'cards_count'  => count($state['cards'] ?? []),
        'state_size'   => strlen($r['state_json']),
        'columns'      => array_map(fn($c) => ['id'=>$c['id'],'title'=>$c['title']], $state['columns'] ?? []),
    ];
}

jsonResponse(['success' => true, 'projects' => $result]);
