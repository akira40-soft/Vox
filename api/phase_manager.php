<?php
/**
 * api/phase_manager.php - Election Room Phase Manager
 * Handles phase checking and manual phase advancement for organizers.
 */
require_once '../config/helpers.php';

// BLINDAGEM DE OUTPUT: Prevenir que Notices quebrem o JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// Ensure user is logged in
$userId = requireAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido ou sessão expirada.']);
        exit;
    }
}

try {
    switch ($action) {
        case 'check':
            $salaId = (int)($_GET['sala_id'] ?? $_GET['sala'] ?? 0);
            
            if (!$salaId) {
                throw new Exception("ID da sala inválido.");
            }
            
            // Auto-sync phase in database before returning results
            syncRoomPhase($pdo, $salaId);
            
            // Get room details including phase info
            $stmt = $pdo->prepare("
                SELECT id, nome, fase_atual, estado, data_votacao_inicio, data_votacao_fim, 
                       data_campanha_inicio, data_campanha_fim, organizador_id
                FROM salas_eleitorais WHERE id = ?
            ");
            $stmt->execute([$salaId]);
            $sala = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sala) {
                throw new Exception("Sala não encontrada.");
            }
            
            // Compute current phase details
            $now = new DateTime();
            $phaseData = computeRoomPhase($sala, $now);
            
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'fase' => [
                    'nome' => $phaseData['fase'],
                    'segundos_restantes' => $phaseData['seconds_left'],
                    'proxima' => $phaseData['next_fase'],
                    'estado_db' => $sala['estado'],
                    'fase_db' => $sala['fase_atual']
                ],
                'is_organizer' => ($sala['organizador_id'] == $userId || $_SESSION['user_role'] === 'admin')
            ]);
            exit;

        case 'advance':
            $salaId = (int)($_POST['sala_id'] ?? 0);
            
            // Permission check: Admin or Organizer
            $stmt = $pdo->prepare("SELECT organizador_id FROM salas_eleitorais WHERE id = ?");
            $stmt->execute([$salaId]);
            $orgId = $stmt->fetchColumn();
            
            if (!$orgId) {
                throw new Exception("Sala não encontrada.");
            }
            
            if ($orgId != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception("Não tem permissão para avançrar a fase desta sala.");
            }
            
            // Get current phase
            $stmt = $pdo->prepare("SELECT fase FROM salas_eleitorais WHERE id = ?");
            $stmt->execute([$salaId]);
            $currentPhase = $stmt->fetchColumn();
            
            // Define phase transitions
            $phaseTransitions = [
                'rascunho' => 'campanha',
                'campanha' => 'votacao',
                'votacao' => 'resultados',
                'resultados' => 'terminada',
                'terminada' => 'terminada' // No change
            ];
            
            $newPhase = $phaseTransitions[$currentPhase] ?? $currentPhase;
            
            if ($newPhase === $currentPhase) {
                throw new Exception("Não é possível avançar a fase atual.");
            }
            
            // Update phase
            $stmt = $pdo->prepare("UPDATE salas_eleitorais SET fase = ? WHERE id = ?");
            $stmt->execute([$newPhase, $salaId]);
            
            // Log the action
            auditLog($userId, 'advance_phase', "Fase alterada de '$currentPhase' para '$newPhase' na sala ID=$salaId");
            
            $response = [
                'success' => true,
                'message' => "Fase avançada para: " . ucfirst($newPhase),
                'new_phase' => $newPhase,
                'old_phase' => $currentPhase
            ];
            break;

        default:
            $response = ['success' => false, 'message' => 'Ação desconhecida.'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
