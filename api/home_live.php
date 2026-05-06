<?php
/**
 * api/home_live.php - Vox Electoral Platform
 * Returns real-time vote counts and phase data for a list of rooms.
 */
require_once '../config/helpers.php';
$userId = requireAuth();

header('Content-Type: application/json');

$roomIds = $_GET['rooms'] ?? '';
if (empty($roomIds)) {
    echo json_encode(['success' => false, 'message' => 'No rooms provided']);
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $roomIds)));
if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid room IDs']);
    exit;
}

try {
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    // Fetch total votes
    $stmt = $pdo->prepare("
        SELECT sala_id, COUNT(*) as total_votos 
        FROM votos 
        WHERE sala_id IN ($placeholders)
        GROUP BY sala_id
    ");
    $stmt->execute($ids);
    $votes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fetch current state
    $stmt = $pdo->prepare("
        SELECT id, estado, fase_atual
        FROM salas_eleitorais 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $states = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($ids as $id) {
        $stateInfo = array_filter($states, fn($s) => $s['id'] == $id);
        $stateInfo = reset($stateInfo);
        
        $results[$id] = [
            'total_votos' => $votes[$id] ?? 0,
            'estado' => $stateInfo['estado'] ?? 'desconhecido',
            'fase' => $stateInfo['fase_atual'] ?? 'desconhecido'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $results
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
