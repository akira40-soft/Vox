<?php
/**
 * api/messages.php - Direct Messaging Handler
 */
require_once '../config/helpers.php';
$userId = requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida.'];

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
        exit;
    }
}

try {
    switch ($action) {
        case 'send':
            $destId = (int)($_POST['destinatario_id'] ?? 0);
            $salaId = (int)($_POST['sala_id'] ?? 0);
            $conteudo = sanitize($_POST['conteudo'] ?? '');

            if ($destId <= 0 || empty($conteudo)) throw new Exception("Dados inválidos.");

            // Policy: Voters can talk to each other. Candidates cannot start DMs (to prevent spam).
            $userRole = $_SESSION['user_role'] ?? 'voter';
            if ($userRole === 'candidato') {
                // Check if the chat was already started by a voter? 
                // For now, let's keep it simple: Candidates can only REPLY.
                // But we'll skip the strict check for now as per user's "Candidates cannot send msgs entt eles não vão ver essa aba de conversas e directs".
                throw new Exception("Candidatos não podem enviar mensagens diretas nesta fase.");
            }

            $stmt = $pdo->prepare("INSERT INTO mensagens_diretas (sala_id, remetente_id, destinatario_id, conteudo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$salaId, $userId, $destId, $conteudo]);

            // Notify destinatário
            notifyUser($destId, 'mensagem', "Recebeu uma nova mensagem de " . $_SESSION['user_nome'], "sala_detalhes.php?id=$salaId&tab=mensagens");

            $response = ['success' => true, 'message' => 'Mensagem enviada!'];
            break;

        case 'fetch_convo':
            $otherId = (int)($_GET['with_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT * FROM mensagens_diretas 
                WHERE ((remetente_id = ? AND destinatario_id = ?) OR (remetente_id = ? AND destinatario_id = ?))
                ORDER BY criado_em ASC
            ");
            $stmt->execute([$userId, $otherId, $otherId, $userId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'messages' => $messages];
            break;

        case 'list_contacts':
             $salaId = (int)($_GET['sala_id'] ?? 0);
             
             // 1. People you have chatted with
             $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.nome_completo, u.avatar, u.role,
                       (SELECT conteudo FROM mensagens_diretas 
                        WHERE (remetente_id = u.id AND destinatario_id = ?) 
                           OR (remetente_id = ? AND destinatario_id = u.id)
                        ORDER BY criado_em DESC LIMIT 1) as ultima_msg
                FROM users u
                JOIN mensagens_diretas m ON (m.remetente_id = u.id OR m.destinatario_id = u.id)
                WHERE (m.remetente_id = ? OR m.destinatario_id = ?) AND u.id != ?
             ");
             $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
             $chatHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

             // 2. Candidates and Organizer in this room (to start new campaigns chats)
             $stmt = $pdo->prepare("
                SELECT u.id, u.nome_completo, u.avatar, u.role
                FROM users u
                LEFT JOIN candidatos c ON c.user_id = u.id AND c.sala_id = ?
                LEFT JOIN salas_eleitorais s ON s.organizador_id = u.id AND s.id = ?
                WHERE (c.id IS NOT NULL OR s.id IS NOT NULL) AND u.id != ?
             ");
             $stmt->execute([$salaId, $salaId, $userId]);
             $campaignContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

             // Merge and unique by ID
             $allContacts = array_merge($chatHistory, $campaignContacts);
             $uniqueContacts = [];
             foreach($allContacts as $c) {
                 if (!isset($uniqueContacts[$c['id']])) {
                     $uniqueContacts[$c['id']] = $c;
                 }
             }

             $response = ['success' => true, 'contacts' => array_values($uniqueContacts)];
             break;

        default:
            throw new Exception("Ação desconhecida.");
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
