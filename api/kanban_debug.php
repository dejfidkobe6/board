<?php
// TEMPORARY DEBUG — remove after data recovery
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'not logged in']); exit; }
$userId = (int)$_SESSION['user_id'];

$db = getDB();

$stmt = $db->prepare('
    SELECT k.project_id, k.updated_at, k.state_json, p.name as project_name
    FROM project_kanban_state k
    JOIN projects p ON p.id = k.project_id
    JOIN project_members pm ON pm.project_id = k.project_id AND pm.user_id = ?
    ORDER BY k.updated_at DESC
');
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

$result = [];
foreach ($rows as $r) {
    $state = json_decode($r['state_json'], true) ?? [];
    $result[] = [
        'project_id'    => $r['project_id'],
        'project_name'  => $r['project_name'],
        'updated_at'    => $r['updated_at'],
        'columns_count' => count($state['columns'] ?? []),
        'cards_count'   => count($state['cards'] ?? []),
        'state_bytes'   => strlen($r['state_json']),
        'columns'       => array_map(fn($c) => $c['title'] ?? '?', $state['columns'] ?? []),
    ];
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'user_id' => $userId, 'projects' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
