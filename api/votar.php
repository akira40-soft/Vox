<?php
/**
 * api/votar.php - AJAX Voting Endpoint
 */
require_once '../config/helpers.php';
$userId = requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$salaId = (int)($_POST['sala_id'] ?? 0);
$temaId = (int)($_POST['tema_id'] ?? 0);
$candId = (int)($_POST['candidato_id'] ?? 0);
$opcaoSimNao = sanitize($_POST['opcao_sim_nao'] ?? '');

// CSRF Check
$token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Sync phase before checking status
    syncRoomPhase($pdo, $salaId);

    // 1. Fetch room and verify state
    $stmt = $pdo->prepare("SELECT * FROM salas_eleitorais WHERE id = ?");
    $stmt->execute([$salaId]);
    $sala = $stmt->fetch();

    if (!$sala) throw new Exception("Sala não encontrada.");
    
    $userRole = $_SESSION['user_role'] ?? 'eleitor';
    $isAuthorized = ($sala['organizador_id'] == $userId || $userRole === 'admin');
    
    if ($sala['estado'] !== 'ativa' && !$isAuthorized) {
        throw new Exception("Esta sala não está em fase de votação.");
    }

    // 2. Verify if user already voted in this TEMA
    $stmt = $pdo->prepare("SELECT id FROM votos WHERE sala_id = ? AND tema_id = ? AND user_id = ?");
    $stmt->execute([$salaId, $temaId, $userId]);
    if ($stmt->fetch()) throw new Exception("Já votou neste tema.");

    // 3. Register the vote
    $rawSecret = $userId . '-' . $salaId . '-' . $temaId . '-' . time() . '-' . VOTE_SECRET;
    $voteHash = hash('sha256', $rawSecret);
    $ipVotante = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO votos (sala_id, user_id, tema_id, candidato_id, opcao_sim_nao, voto_hash, ip_votante)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $salaId, 
        $userId, 
        $temaId, 
        $candId ?: null, 
        $opcaoSimNao ?: null, 
        $voteHash, 
        $ipVotante
    ]);

    // 4. Update candidate totals if applicable
    if ($candId) {
        $pdo->prepare("UPDATE candidatos SET votos_totais = votos_totais + 1 WHERE id = ?")
            ->execute([$candId]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Voto registado com sucesso!',
        'hash' => $voteHash
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
