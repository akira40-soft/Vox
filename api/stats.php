<?php
/**
 * api/stats.php - Real-time Stats for Social Hub
 */
require_once '../config/helpers.php';
// No mandatory auth for ticker stats (publicly visible room data), 
// but we'll check if room is public later.

$action = $_GET['action'] ?? '';
$salaId = (int)($_GET['sala_id'] ?? 0);

if ($salaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sala inválida']);
    exit;
}

try {
    switch ($action) {
        case 'room_summary':
            // Total Votes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE sala_id = ?");
            $stmt->execute([$salaId]);
            $totalVotos = $stmt->fetchColumn();

            // Total Posts (Campaign)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM campanhas WHERE sala_id = ?");
            $stmt->execute([$salaId]);
            $totalPosts = $stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'total_votos' => $totalVotos,
                'total_posts' => $totalPosts
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação não suportada']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
