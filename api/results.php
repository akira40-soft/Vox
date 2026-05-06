<?php
/**
 * api/results.php - Vox Electoral Platform
 * JSON API endpoint for real-time election results
 *
 * Actions:
 *   GET ?action=results&sala_id=X          - Full results with candidates per theme
 *   GET ?action=stats&sala_id=X            - Aggregate voting stats
 *   GET ?action=theme&sala_id=X&t=Y        - Results for a single theme
 *   GET ?action=export&sala_id=X           - Download results as CSV
 *
 * Returns: JSON (or CSV for export)
 * Requires: Authenticated user for write actions; public read for active rooms
 */

require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$pdo    = getDB();
$action = $_GET['action'] ?? 'results';
$salaId = (int)($_GET['sala_id'] ?? 0);

if ($salaId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'sala_id invalido ou nao fornecido.'
    ]);
    exit;
}

try {
    // Verify sala exists
    $salaStmt = $pdo->prepare("SELECT id, nome, estado FROM salas_eleitorais WHERE id = :id");
    $salaStmt->execute(['id' => $salaId]);
    $sala = $salaStmt->fetch();

    if (!$sala) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Sala nao encontrada.'
        ]);
        exit;
    }

    switch ($action) {

        // ============================
        // Full results per theme
        // ============================
        case 'results':
            $temasStmt = $pdo->prepare("
                SELECT t.id, t.titulo, t.descricao
                FROM temas t
                WHERE t.sala_id = :sala_id
                ORDER BY t.ordem ASC, t.titulo ASC
            ");
            $temasStmt->execute(['sala_id' => $salaId]);
            $temas = $temasStmt->fetchAll();

            $results = [];
            foreach ($temas as $tema) {
                // Get candidates with vote counts
                $candsStmt = $pdo->prepare("
                    SELECT c.id, c.nome, c.proposta, c.foto,
                           c.votos_totais AS votos
                    FROM candidatos c
                    WHERE c.tema_id = :tema_id
                    ORDER BY votos DESC, c.nome ASC
                ");
                $candsStmt->execute(['tema_id' => (int)$tema['id']]);
                $candidatos = $candsStmt->fetchAll();

                $totalVotosTema = array_sum(array_column($candidatos, 'votos'));

                // Calculate percentages
                foreach ($candidatos as &$c) {
                    $c['percentagem'] = $totalVotosTema > 0
                        ? round(((int)$c['votos'] / $totalVotosTema) * 100, 2)
                        : 0.0;
                }
                unset($c);

                $results[] = [
                    'tema_id'      => (int)$tema['id'],
                    'tema_nome'    => $tema['titulo'],
                    'descricao'    => $tema['descricao'],
                    'total_votos'  => $totalVotosTema,
                    'candidatos'   => $candidatos
                ];
            }

            echo json_encode([
                'success'    => true,
                'sala_id'    => $salaId,
                'sala_nome'  => $sala['nome'],
                'estado'     => $sala['estado'],
                'resultados' => $results
            ]);
            break;

        // ============================
        // Audit Trail (Vote Hashes)
        // ============================
        case 'audit':
            $auditStmt = $pdo->prepare("
                SELECT voto_hash, criado_em, tema_id
                FROM votos
                WHERE sala_id = :sala_id
                ORDER BY criado_em DESC
                LIMIT 50
            ");
            $auditStmt->execute(['sala_id' => $salaId]);
            $votes = $auditStmt->fetchAll();

            echo json_encode([
                'success' => true,
                'sala_id' => $salaId,
                'votes'   => $votes,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // ============================
        // Aggregate stats
        // ============================
        case 'stats':
            $statsStmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_votos,
                    COUNT(DISTINCT user_id) AS total_votantes,
                    COUNT(DISTINCT tema_id) AS temas_com_votos,
                    COUNT(DISTINCT user_id) / NULLIF(
                        (SELECT COUNT(*) FROM votos v2 JOIN candidatos c2 ON v2.candidato_id = c2.id
                         JOIN temas t2 ON c2.tema_id = t2.id WHERE t2.sala_id = v.sala_id), 0
                    ) * 100 AS media_participacao
                FROM votos v
                WHERE sala_id = :sala_id
            ");
            // Simpler query to avoid subquery issues
            $statsStmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_votos,
                    COUNT(DISTINCT user_id) AS total_votantes,
                    COUNT(DISTINCT tema_id) AS temas_com_votos
                FROM votos
                WHERE sala_id = :sala_id
            ");
            $statsStmt->execute(['sala_id' => $salaId]);
            $stats = $statsStmt->fetch();

            echo json_encode([
                'success'       => true,
                'sala_id'       => $salaId,
                'total_votos'   => (int)$stats['total_votos'],
                'total_votantes'=> (int)$stats['total_votantes'],
                'temas_com_votos' => (int)$stats['temas_com_votos'],
                'timestamp'     => date('Y-m-d H:i:s')
            ]);
            break;

        // ============================
        // Single theme results
        // ============================
        case 'theme':
            $temaId = (int)($_GET['t'] ?? 0);
            if ($temaId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do tema nao fornecido.'
                ]);
                exit;
            }

            // Verify theme belongs to this sala
            $tCheck = $pdo->prepare("
                SELECT id, titulo, descricao FROM temas WHERE id = :id AND sala_id = :sala_id
            ");
            $tCheck->execute(['id' => $temaId, 'sala_id' => $salaId]);
            $temaData = $tCheck->fetch();

            if (!$temaData) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tema nao encontrado nesta sala.'
                ]);
                exit;
            }

            $candsStmt = $pdo->prepare("
                SELECT c.id, c.nome, c.proposta, c.foto,
                       c.votos_totais AS votos
                FROM candidatos c
                WHERE c.tema_id = :tema_id
                ORDER BY votos DESC, c.nome ASC
            ");
            $candsStmt->execute(['tema_id' => $temaId]);
            $candidatos = $candsStmt->fetchAll();

            $totalVotos = array_sum(array_column($candidatos, 'votos'));
            foreach ($candidatos as &$c) {
                $c['percentagem'] = $totalVotos > 0
                    ? round(((int)$c['votos'] / $totalVotos) * 100, 2)
                    : 0.0;
            }
            unset($c);

            echo json_encode([
                'success'     => true,
                'sala_id'     => $salaId,
                'tema_id'     => $temaId,
                'tema_nome'   => $temaData['titulo'],
                'total_votos' => $totalVotos,
                'candidatos'  => $candidatos,
                'timestamp'   => date('Y-m-d H:i:s')
            ]);
            break;

        // ============================
        // Export as CSV
        // ============================
        case 'export':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="resultados_sala_' . $salaId . '_' . date('Ymd_His') . '.csv"');
            header('Pragma: no-cache');

            $output = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            echo "\xEF\xBB\xBF";

            // Header
            fputcsv($output, ['Sala', $sala['nome']], ';');
            fputcsv($output, ['Status', ucfirst($sala['estado'])], ';');
            fputcsv($output, ['Data de Exportacao', date('d/m/Y H:i:s')], ';');
            fputcsv($output, [], ';');

            // Column headers
            fputcsv($output, [
                'Tema',
                'Candidato',
                'Votos',
                'Percentagem'
            ], ';');

            // Data
            $temasStmt = $pdo->prepare("
                SELECT t.id, t.titulo
                FROM temas t
                WHERE t.sala_id = :sala_id
                ORDER BY t.ordem ASC
            ");
            $temasStmt->execute(['sala_id' => $salaId]);

            while ($tema = $temasStmt->fetch()) {
                $candsStmt = $pdo->prepare("
                    SELECT c.nome,
                           c.votos_totais AS votos
                    FROM candidatos c
                    WHERE c.tema_id = :tema_id
                    ORDER BY votos DESC, c.nome ASC
                ");
                $candsStmt->execute(['tema_id' => (int)$tema['id']]);
                $cands = $candsStmt->fetchAll();
                $totalTema = array_sum(array_column($cands, 'votos'));

                foreach ($cands as $c) {
                    $pct = $totalTema > 0
                        ? round(((int)$c['votos'] / $totalTema) * 100, 2)
                        : 0.0;
                    fputcsv($output, [
                        $tema['titulo'],
                        $c['nome'],
                        (int)$c['votos'],
                        $pct . '%'
                    ], ';');
                }

                // Subtotal
                fputcsv($output, [
                    $tema['titulo'] . ' (TOTAL)',
                    '',
                    $totalTema,
                    '100%'
                ], ';');
                fputcsv($output, [], ';');
            }

            fclose($output);
            exit;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Acao invalida. Aces disponiveis: results, stats, theme, export'
            ]);
            break;
    }

} catch (PDOException $e) {
    error_log("Results API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor. Por favor, tente mais tarde.'
    ]);
}
