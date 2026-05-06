<?php
/**
 * Create Voting Session API Endpoint
 * POST /api/votos_criar.php
 * 
 * Creates new voting session (sala eleitoral) with temas and candidatos
 * Only for users with role 'organizador'
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../config/helpers.php';
require_once '../config/constants.php';

try {
    $userId = requireAuth();
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Verify organizador role
    if ($_SESSION['user_role'] !== ROLE_ORGANIZADOR) {
        http_response_code(403);
        throw new Exception('Only organizadores can create voting sessions');
    }
    
    if ($method !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $nome = trim($input['nome'] ?? '');
    $descricao = trim($input['descricao'] ?? '');
    $tipo_votacao = trim($input['tipo_votacao'] ?? 'sim_nao');
    $temas = $input['temas'] ?? [];
    $candidatos = $input['candidatos'] ?? [];
    $provincia = intval($input['provincia'] ?? 0);
    
    // Validation
    if (empty($nome) || strlen($nome) < 3 || strlen($nome) > 255) {
        throw new Exception('Nome inválido (3-255 caracteres)');
    }
    
    if (empty($temas) || count($temas) == 0) {
        throw new Exception('Mínimo 1 tema requerido');
    }
    
    if (!in_array($tipo_votacao, ['sim_nao', 'multipla', 'ranking', 'multiplos_votos'])) {
        throw new Exception('Tipo de votação inválido');
    }
    
    // Verify provincia exists
    if ($provincia > 0) {
        $provStmt = $pdo->prepare("SELECT id FROM provincias WHERE id = ?");
        $provStmt->execute([$provincia]);
        if (!$provStmt->fetch()) {
            throw new Exception('Provincia inválida');
        }
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Create sala eleitoral
        $salaStmt = $pdo->prepare("
            INSERT INTO salas_eleitorais 
            (nome, descricao, organizador_id, provincia_origem, estado, criado_em)
            VALUES (?, ?, ?, ?, 'rascunho', NOW())
        ");
        $salaStmt->execute([$nome, $descricao, $userId, $provincia]);
        $salaId = $pdo->lastInsertId();
        
        // 2. Create temas
        $temaIds = [];
        foreach ($temas as $index => $temaNome) {
            $temaNome = trim($temaNome);
            if (empty($temaNome)) continue;
            
            $temaStmt = $pdo->prepare("
                INSERT INTO temas (sala_id, titulo, tipo_votacao, posicao, criado_em)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $temaStmt->execute([$salaId, $temaNome, $tipo_votacao, $index]);
            $temaIds[] = $pdo->lastInsertId();
        }
        
        if (empty($temaIds)) {
            throw new Exception('Nenhum tema válido fornecido');
        }
        
        // 3. Create candidatos (for non-sim_nao voting)
        if ($tipo_votacao !== 'sim_nao' && !empty($candidatos)) {
            foreach ($candidatos as $index => $candidatoNome) {
                $candidatoNome = trim($candidatoNome);
                if (empty($candidatoNome)) continue;
                
                // Get or create candidato user
                $candStmt = $pdo->prepare("
                    SELECT id FROM candidatos 
                    WHERE sala_id = ? AND nome = ?
                    LIMIT 1
                ");
                $candStmt->execute([$salaId, $candidatoNome]);
                $existing = $candStmt->fetch();
                
                if (!$existing) {
                    $insertCandStmt = $pdo->prepare("
                        INSERT INTO candidatos (sala_id, nome, user_id, posicao, criado_em)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $insertCandStmt->execute([$salaId, $candidatoNome, null, $index]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        auditLog($userId, 'votacao_criada', "Sala ID: $salaId, Temas: " . count($temaIds));
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Votação criada com sucesso',
            'sala_id' => $salaId,
            'sala_nome' => $nome,
            'temas_count' => count($temaIds),
            'candidatos_count' => count($candidatos)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
