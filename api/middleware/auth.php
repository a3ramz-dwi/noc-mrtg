<?php
declare(strict_types=1);

// API authentication middleware
// Returns user array if authenticated, sends 401 and exits if not.

function apiGetBearerToken(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    // Check Authorization header
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        return trim($m[1]);
    }
    // Check X-API-Token header
    return $headers['X-API-Token'] ?? $headers['x-api-token'] ?? ($_SERVER['HTTP_X_API_TOKEN'] ?? null);
}

function apiRequireAuth(): array
{
    // Try session first (for browser-based requests)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!empty($_SESSION['user_id'])) {
        $db   = \NOC\Core\Database::getInstance();
        $user = $db->fetch(
            'SELECT id, username, full_name, role, status FROM users WHERE id = ? AND status = ?',
            [$_SESSION['user_id'], 'active']
        );
        if ($user) {
            return $user;
        }
    }
    // Try token authentication
    $token = apiGetBearerToken();
    if ($token) {
        $db   = \NOC\Core\Database::getInstance();
        $user = $db->fetch(
            'SELECT id, username, full_name, role, status FROM users WHERE api_token = ? AND status = ?',
            [$token, 'active']
        );
        if ($user) {
            return $user;
        }
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please provide valid credentials.']);
    exit;
}

function apiJsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiSuccess(mixed $data = null, string $message = 'Success', int $status = 200): void
{
    apiJsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}

function apiError(string $message, int $status = 400, array $errors = []): void
{
    $r = ['success' => false, 'message' => $message];
    if ($errors) {
        $r['errors'] = $errors;
    }
    apiJsonResponse($r, $status);
}

function apiGetId(int $segment = 1): ?int
{
    $segments = $GLOBALS['_API']['segments'] ?? [];
    $val      = $segments[$segment] ?? null;
    return is_numeric($val) ? (int) $val : null;
}

function apiGetMethod(): string
{
    return $GLOBALS['_API']['method'] ?? 'GET';
}

function apiGetBody(): array
{
    return $GLOBALS['_API']['body'] ?? [];
}
