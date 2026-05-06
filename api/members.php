<?php
/**
 * api/members.php - Member Management API
 */
require_once '../config/helpers.php';
$userId = requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'add':
            if ($method !== 'POST') throw new Exception('Método inválido.');
            
            $salaId = (int)$_POST['sala_id'];
            $targetUserId = (int)$_POST['user_id'];
            $papel = sanitize($_POST['papel'] ?? 'eleitor');
            
            // Verify permissions
            $stmt = $pdo->prepare("SELECT organizador_id, nome FROM salas_eleitorais WHERE id = ?");
            $stmt->execute([$salaId]);
            $sala = $stmt->fetch();
            
            if (!$sala) throw new Exception('Sala não encontrada.');
            if ($sala['organizador_id'] != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception('Sem permissão para adicionar membros.');
            }
            
            // Add to sala_membros with PostgreSQL ON CONFLICT DO NOTHING
            $stmtIns = $pdo->prepare("INSERT INTO sala_membros (sala_id, user_id, papel) VALUES (?, ?, ?) ON CONFLICT (sala_id, user_id) DO NOTHING");
            if ($stmtIns->execute([$salaId, $targetUserId, $papel])) {
                // Verify if it actually inserted or ignored
                if ($stmtIns->rowCount() > 0) {
                    // Notify user
                    $msg = "Foste adicionado(a) diretamente como " . ($papel === 'candidato' ? 'Candidato(a)' : 'Eleitor(a)') . " na sala " . $sala['nome'] . "!";
                    notifyUser($targetUserId, 'info', $msg, "sala_detalhes.php?id=" . $salaId);
                    
                    echo json_encode(['success' => true, 'message' => 'Membro adicionado com sucesso!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Utilizador já é membro desta sala.']);
                }
            } else {
                throw new Exception('Erro ao adicionar membro.');
            }
            break;
            
        case 'remove':
            if ($method !== 'POST') throw new Exception('Método inválido.');
            
            $salaId = (int)$_POST['sala_id'];
            $targetUserId = (int)$_POST['user_id'];
            
            // Verify permissions
            $stmt = $pdo->prepare("SELECT organizador_id FROM salas_eleitorais WHERE id = ?");
            $stmt->execute([$salaId]);
            $sala = $stmt->fetch();
            
            if (!$sala) throw new Exception('Sala não encontrada.');
            if ($sala['organizador_id'] != $userId && $_SESSION['user_role'] !== 'admin') {
                throw new Exception('Sem permissão para remover membros.');
            }
            
            // Remove from sala_membros
            $stmtDel = $pdo->prepare("DELETE FROM sala_membros WHERE sala_id = ? AND user_id = ?");
            $stmtDel->execute([$salaId, $targetUserId]);
            
            echo json_encode(['success' => true, 'message' => 'Membro removido com sucesso.']);
            break;
            
        case 'list':
            $salaId = (int)$_GET['sala_id'];
            
            $stmt = $pdo->prepare("
                SELECT m.user_id, m.papel, m.entrou_em as criado_em, u.nome_completo, u.username
                FROM sala_membros m
                JOIN users u ON m.user_id = u.id
                WHERE m.sala_id = ?
                ORDER BY m.entrou_em DESC
            ");
            $stmt->execute([$salaId]);
            $members = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'members' => $members]);
            break;
            
        default:
            throw new Exception('Ação inválida.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
