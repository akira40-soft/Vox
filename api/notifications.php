<?php
/**
 * api/notifications.php - Vox Electoral Platform
 * JSON API endpoint for user notifications
 *
 * Actions:
 *   GET ?action=count        - Returns unread notification count
 *   GET ?action=list         - Returns list of recent notifications
 *   POST ?action=mark_read   - Marks a specific notification as read
 *   POST ?action=mark_all    - Marks all notifications as read
 *   POST ?action=delete      - Deletes a notification
 *
 * Returns: JSON
 * Requires: Authenticated user (session), CSRF token for POST requests
 */

require_once __DIR__ . '/../config/helpers.php';
$pdo = getDB();

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Nao autenticado.'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'count';

// For POST actions, verify CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? $_POST['token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || $token !== $sessionToken) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Token de seguranca invalido.'
        ]);
        exit;
    }
}

try {
    switch ($action) {

        // ---- Unread count ----
        case 'count':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS total
                FROM notificacoes
                WHERE user_id = :uid AND lida = FALSE
            ");
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch();
            echo json_encode([
                'success' => true,
                'unread'  => (int)$row['total']
            ]);
            break;

        // ---- List recent notifications ----
        case 'list':
            $limit  = min((int)($_GET['limit'] ?? 10), 50);
            $offset = max((int)($_GET['offset'] ?? 0), 0);

            $stmt = $pdo->prepare("
                SELECT id, mensagem, tipo, lida, link, criado_em
                FROM notificacoes
                WHERE user_id = :uid
                ORDER BY lida ASC, criado_em DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':uid',    $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $notificacoes = $stmt->fetchAll();

            // Format dates
            foreach ($notificacoes as &$n) {
                $n['criado_em_formatada'] = date('d/m/Y H:i', strtotime($n['criado_em']));
                $n['relative_time'] = tempoRelativo($n['criado_em']);
            }
            unset($n);

            echo json_encode([
                'success' => true,
                'notifications' => $notificacoes
            ]);
            break;

        // ---- Mark single notification as read ----
        case 'mark_read':
            $notifId = (int)($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
            if ($notifId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID da notificacao invalido.'
                ]);
                exit;
            }
            $stmt = $pdo->prepare("
                UPDATE notificacoes
                SET lida = TRUE
                WHERE id = :id AND user_id = :uid AND lida = FALSE
            ");
            $stmt->execute(['id' => $notifId, 'uid' => $userId]);

            // Return updated count
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) AS total FROM notificacoes
                WHERE user_id = :uid AND lida = FALSE
            ");
            $countStmt->execute(['uid' => $userId]);
            $count = (int)$countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'unread'  => $count,
                'message' => 'Notificacao marcada como lida.'
            ]);
            break;

        // ---- Mark all notifications as read ----
        case 'mark_all':
            $stmt = $pdo->prepare("
                UPDATE notificacoes
                SET lida = TRUE
                WHERE user_id = :uid AND lida = FALSE
            ");
            $stmt->execute(['uid' => $userId]);
            $affected = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'updated' => $affected,
                'unread'  => 0,
                'message' => 'Todas as notificacoes marcadas como lidas.'
            ]);
            break;

        // ---- Delete notification ----
        case 'delete':
            $notifId = (int)($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
            if ($notifId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID da notificacao invalido.'
                ]);
                exit;
            }
            $stmt = $pdo->prepare("
                DELETE FROM notificacoes
                WHERE id = :id AND user_id = :uid
            ");
            $stmt->execute(['id' => $notifId, 'uid' => $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Notificacao eliminada.'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Acao invalida. Aces: count, list, mark_read, mark_all, delete'
            ]);
            break;
    }

} catch (PDOException $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Por favor, tente mais tarde.'
    ]);
}

/**
 * Helper: human-readable relative time in Portuguese
 */
function tempoRelativo($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0)  return $diff->y . " ano(s) atras";
    if ($diff->m > 0)  return $diff->m . " mes(es) atras";
    if ($diff->d > 0)  return $diff->d . " dia(s) atras";
    if ($diff->h > 0)  return $diff->h . " hora(s) atras";
    if ($diff->i > 0)  return $diff->i . " minuto(s) atras";
    return "Agora mesmo";
}
