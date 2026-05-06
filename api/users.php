<?php
/**
 * api/users.php — Vox V2.0 User lookup for @username invite system
 *
 * GET ?action=search&q=@username  → search users by username/name
 * GET ?action=invite_status&sala_id=N → list pending invites for a sala
 * POST action=invite   → send a candidate invite to a user
 * POST action=accept   → accept an invite (the invited user)
 * POST action=decline  → decline an invite
 */
require_once '../config/helpers.php';
$userId = requireAuth();
header('Content-Type: application/json; charset=utf-8');
ob_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
        exit;
    }
}

try {
    switch ($action) {

        // ── Search users by @username or name ────────────────────────────────
        case 'search':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 2) throw new Exception('Mínimo 2 caracteres para pesquisar.');

            // Strip leading @ if present
            $q = ltrim($q, '@');

            $stmt = $pdo->prepare("
                SELECT id, nome_completo, username, avatar, role
                FROM users
                WHERE estado = 'ativo'
                  AND id != ?
                  AND (username LIKE ? OR nome_completo LIKE ?)
                LIMIT 10
            ");
            $stmt->execute([$userId, "%$q%", "%$q%"]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $users = array_map(fn($u) => [
                'id'           => $u['id'],
                'nome_completo'=> $u['nome_completo'],
                'username'     => $u['username'] ? '@' . $u['username'] : null,
                'avatar'       => $u['avatar'],
                'role'         => $u['role'],
            ], $users);

            $json = json_encode(['success' => true, 'users' => $users]);
            if ($json === false) {
                // Handle encoding errors
                $users = array_map(function($u) {
                    $u['nome_completo'] = mb_convert_encoding($u['nome_completo'], 'UTF-8', 'UTF-8');
                    return $u;
                }, $users);
                $json = json_encode(['success' => true, 'users' => $users], JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            ob_clean();
            echo $json;
            break;

        // ── Send candidate invite ─────────────────────────────────────────────
        case 'invite':
            $salaId      = (int)($_POST['sala_id'] ?? 0);
            $convidadoId = (int)($_POST['user_id'] ?? 0);
            $temaId      = isset($_POST['tema_id']) ? (int)$_POST['tema_id'] : null;

            // Only organizer or admin can invite candidates
            $stmt = $pdo->prepare("SELECT organizador_id, nome FROM salas_eleitorais WHERE id=?");
            $stmt->execute([$salaId]);
            $sala = $stmt->fetch();

            if (!$sala || ($sala['organizador_id'] != $userId && !in_array($_SESSION['user_role'] ?? '', ['admin', 'organizador']))) {
                throw new Exception('Sem permissão para convidar candidatos.');
            }

            // Check user isn't already a candidate or has a pending invite
            $check = $pdo->prepare("
                SELECT id FROM candidatos WHERE sala_id=? AND user_id=?
            ");
            $check->execute([$salaId, $convidadoId]);
            if ($check->fetch()) throw new Exception('Este utilizador já é candidato nesta sala.');

            $checkInvite = $pdo->prepare("
                SELECT id FROM convites_sala WHERE sala_id=? AND user_id_convidado=? AND estado='pendente'
            ");
            $checkInvite->execute([$salaId, $convidadoId]);
            if ($checkInvite->fetch()) throw new Exception('Convite já enviado e pendente.');

            // Create invite
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("
                INSERT INTO convites_sala (sala_id, user_id_convidado, token_convite, papel, estado)
                VALUES (?, ?, ?, 'candidato', 'pendente')
            ")->execute([$salaId, $convidadoId, $token]);

            // Notify user
            notifyUser($convidadoId, 'convite',
                "🏅 Foste convidado(a) para ser candidato(a) na eleição \"{$sala['nome']}\". Aceita ou recusa no teu perfil.",
                "notificacoes.php?convite=$token"
            );

            echo json_encode(['success' => true, 'message' => 'Convite enviado!']);
            break;

        // ── Accept invite ─────────────────────────────────────────────────────
        case 'accept':
            $token = sanitize($_POST['token'] ?? '');
            if (empty($token)) throw new Exception('Token inválido.');

            $stmt = $pdo->prepare("
                SELECT c.*, s.nome as sala_nome
                FROM convites_sala c
                JOIN salas_eleitorais s ON c.sala_id = s.id
                WHERE c.token_convite=? AND c.user_id_convidado=? AND c.estado='pendente'
            ");
            $stmt->execute([$token, $userId]);
            $convite = $stmt->fetch();
            if (!$convite) throw new Exception('Convite não encontrado ou já processado.');

            // Get user name for the candidatos record
            $user = $pdo->prepare("SELECT nome_completo FROM users WHERE id=?");
            $user->execute([$userId]);
            $u = $user->fetch();

            // Insert into candidatos (Using ON CONFLICT for PG compatibility)
            $pdo->prepare("
                INSERT INTO candidatos (user_id, sala_id, nome, criado_por)
                VALUES (?, ?, ?, ?) ON CONFLICT (user_id, sala_id) DO NOTHING
            ")->execute([$userId, $convite['sala_id'], $u['nome_completo'], $convite['sala_id']]);

            // Register as member (Using ON CONFLICT for PG compatibility)
            $pdo->prepare("INSERT INTO sala_membros (sala_id, user_id, papel) VALUES (?,?,'candidato') ON CONFLICT (sala_id, user_id) DO NOTHING")
                ->execute([$convite['sala_id'], $userId]);

            // Send notification to user about acceptance
            notifyUser($userId, 'info', 
                "✅ Perfeito! Agora és candidato(a) na eleição \"" . htmlspecialchars($convite['sala_nome']) . "\". Preenche o teu perfil na sala.",
                "sala_detalhes.php?id=" . $convite['sala_id']
            );

            // Mark invite accepted
            $pdo->prepare("UPDATE convites_sala SET estado='aceite' WHERE token_convite=?")
                ->execute([$token]);

            echo json_encode([
                'success' => true,
                'message' => "Ótimo! Já és candidato(a) em \"{$convite['sala_nome']}\". Preenche o teu perfil na sala.",
                'sala_id' => $convite['sala_id']
            ]);
            break;

        // ── Decline invite ────────────────────────────────────────────────────
        case 'decline':
            $token = sanitize($_POST['token'] ?? '');
            $pdo->prepare("UPDATE convites_sala SET estado='recusado' WHERE token_convite=? AND user_id_convidado=?")
                ->execute([$token, $userId]);
            echo json_encode(['success' => true, 'message' => 'Convite recusado.']);
            break;

        // ── List pending invites for current user ─────────────────────────────
        case 'my_invites':
            $stmt = $pdo->prepare("
                SELECT c.token_convite, c.papel, c.estado, c.criado_em,
                       s.id as sala_id, s.nome as sala_nome
                FROM convites_sala c
                JOIN salas_eleitorais s ON c.sala_id = s.id
                WHERE c.user_id_convidado=? AND c.estado='pendente'
                ORDER BY c.criado_em DESC
            ");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'invites' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── List invites for a sala (organizer) ───────────────────────────────
        case 'sala_invites':
            $salaId = (int)($_GET['sala_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT c.*, u.nome_completo, u.username
                FROM convites_sala c
                LEFT JOIN users u ON c.user_id_convidado = u.id
                WHERE c.sala_id=?
                ORDER BY c.criado_em DESC
            ");
            $stmt->execute([$salaId]);
            echo json_encode(['success' => true, 'invites' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
