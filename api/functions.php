<?php
// Helper functions – committed to repo, included by all API files

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Nejsi přihlášen'], 401);
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, avatar_color FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        jsonResponse(['error' => 'Uživatel nenalezen'], 401);
    }
    return $user;
}

function requireProjectRole(int $projectId, string $minRole): array {
    $user  = requireAuth();
    $roles = ['viewer' => 0, 'member' => 1, 'admin' => 2, 'owner' => 3];

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT pm.role FROM project_members pm
         JOIN projects p ON p.id = pm.project_id
         WHERE pm.project_id = ? AND pm.user_id = ? AND p.is_active = 1'
    );
    $stmt->execute([$projectId, $user['id']]);
    $row = $stmt->fetch();

    if (!$row || ($roles[$row['role']] ?? -1) < ($roles[$minRole] ?? 99)) {
        jsonResponse(['error' => 'Nemáš oprávnění'], 403);
    }
    return array_merge($user, ['role' => $row['role']]);
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
