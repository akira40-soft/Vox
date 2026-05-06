<?php
/**
 * Vox - Sistema Eleitoral Angolano
 * Helpers - shared functions
 */

date_default_timezone_set('Africa/Luanda');

if (session_status() === PHP_SESSION_NONE) {
    // Fix XAMPP permission issue by using a local session directory
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_start();
    generateCSRFToken();
}

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/db.php';

$pdo = getDB();

// Auto-login usando cookie "remember_token" se não estiver logado
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT id, nome_completo, role, estado FROM users WHERE remember_token = ? AND estado = 'ativo'");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome_completo'];
        $_SESSION['user_role'] = $user['role'];
    } else {
        // Token inválido ou conta não está ativa, remove a cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Require authentication
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    return $_SESSION['user_id'];
}

// Require specific role
function requireRole($role) {
    $userId = requireAuth();
    $uRole = strtolower(trim($_SESSION['user_role'] ?? ''));
    
    // Normalize $role for comparison
    $role = strtolower(trim($role));
    
    // Allow access if role matches OR if user is admin/candidate (elevated roles for hub management)
    if ($uRole !== $role && $uRole !== ROLE_ADMIN && $uRole !== ROLE_CANDIDATO) {
        if ($uRole === ROLE_ELEITOR) {
            header('Location: home.php?error=access_denied');
        } else {
            header('Location: dashboard.php?error=access_denied');
        }
        exit;
    }
    return $userId;
}

// Require admin role
function requireAdmin() {
    global $pdo;
    $userId = requireAuth();
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
    return $user;
}

// Format date
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Set flash message
function setFlash($type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Get and clear flash
function getFlash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'type' => $_SESSION['flash_type'] ?? 'info',
            'message' => $_SESSION['flash_message']
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

// Generate unique access code
function generateCode($prefix = 'VOX') {
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// Angola provinces
function getProvinceName($id) {
    $provinces = [
        1 => 'Bengo', 2 => 'Benguela', 3 => 'Bié', 4 => 'Cabinda',
        5 => 'Cuando Cubango', 6 => 'Cuanza Norte', 7 => 'Cuanza Sul',
        8 => 'Cunene', 9 => 'Huambo', 10 => 'Huíla', 11 => 'Luanda',
        12 => 'Lunda Norte', 13 => 'Lunda Sul', 14 => 'Malanje',
        15 => 'Moxico', 16 => 'Namibe', 17 => 'Uíge', 18 => 'Zaire'
    ];
    return $provinces[$id] ?? 'Desconhecida';
}

// Audit log helper
function auditLog($userId, $action, $details) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO auditoria (user_id, acao, detalhes, ip) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $details, $ip]);
}

// Pagination
function paginate($total, $perPage = 10) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    return compact('page', 'perPage', 'totalPages', 'offset');
}

// Vote secret
define('VOTE_SECRET', 'vox-angola-2026-secret-key-!');

/**
 * Notify a user about an event
 */
function notifyUser($userId, $type, $message, $link = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notificacoes (user_id, tipo, mensagem, link) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $message, $link]);
}

/**
 * Compute the correct phase of a room based on its configured dates.
 */
function computeRoomPhase(array $sala, DateTime $now): array {
    $toTs = fn($s) => $s ? (new DateTime($s))->getTimestamp() : null;
    $nowTs = $now->getTimestamp();

    $campInicio  = $toTs($sala['data_campanha_inicio'] ?? null);
    $campFim     = $toTs($sala['data_campanha_fim'] ?? null);
    $votInicio   = $toTs($sala['data_votacao_inicio'] ?? null);
    $votFim      = $toTs($sala['data_votacao_fim'] ?? null);

    // No phase data configured
    if (!$votInicio) {
        return ['fase' => $sala['fase_atual'] ?? 'aguardando', 'seconds_left' => null, 'next_fase' => null];
    }

    $hasCamp = (bool)($sala['permitir_campanha'] ?? true);

    if ($hasCamp && $campInicio && $nowTs < $campInicio) {
        return ['fase' => 'aguardando', 'seconds_left' => $campInicio - $nowTs, 'next_fase' => 'campanha'];
    }
    if ($hasCamp && $campInicio && $nowTs >= $campInicio && $campFim && $nowTs < $campFim) {
        return ['fase' => 'campanha', 'seconds_left' => $campFim - $nowTs, 'next_fase' => 'votacao'];
    }
    
    // If we reach here, either campaign is over or disabled
    if ($nowTs < $votInicio) {
        return ['fase' => 'aguardando', 'seconds_left' => $votInicio - $nowTs, 'next_fase' => 'votacao'];
    }
    
    if ($nowTs >= $votInicio && $votFim && $nowTs < $votFim) {
        return ['fase' => 'votacao', 'seconds_left' => $votFim - $nowTs, 'next_fase' => 'estatisticas'];
    }
    
    if ($votFim && $nowTs >= $votFim) {
        return ['fase' => 'estatisticas', 'seconds_left' => null, 'next_fase' => 'encerrada'];
    }

    return ['fase' => 'aguardando', 'seconds_left' => $votInicio - $nowTs, 'next_fase' => 'votacao'];
}

/**
 * Maps a phase string to the corresponding 'estado' column value.
 */
function phaseToEstado(string $fase): string {
    return match($fase) {
        'campanha'     => 'campanha',
        'votacao'      => 'ativa',
        'estatisticas' => 'finalizada',
        'encerrada'    => 'finalizada',
        'arquivada'    => 'cancelada',
        default        => 'rascunho',
    };
}

/**
 * Synchronizes a room's database state with its chronological phase.
 */
function syncRoomPhase($pdo, $salaId) {
    $stmt = $pdo->prepare("SELECT * FROM salas_eleitorais WHERE id = ?");
    $stmt->execute([$salaId]);
    $sala = $stmt->fetch();
    if (!$sala) return false;

    $now = new DateTime();
    $phases = computeRoomPhase($sala, $now);
    $novaFase = $phases['fase'];

    if ($novaFase !== $sala['fase_atual']) {
        $novoEstado = phaseToEstado($novaFase);
        $pdo->prepare("UPDATE salas_eleitorais SET fase_atual = ?, estado = ? WHERE id = ?")
            ->execute([$novaFase, $novoEstado, $salaId]);
        
        // Audit log the transition
        $pdo->prepare("INSERT INTO auditoria (user_id, acao, detalhes) VALUES (NULL, 'PHASE_SYNC', ?)")
            ->execute(["Sala #$salaId mudou automaticamente de '{$sala['fase_atual']}' para '$novaFase'"]);
            
        return true;
    }
    return false;
}
