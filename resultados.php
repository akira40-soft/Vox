<?php
/**
 * resultados.php - Vox Electoral Platform
 * Display voting results for an electoral room
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$salaId = (int)($_GET['sala'] ?? 0);

if ($salaId <= 0) {
    setFlash('error', 'Sala invalida.');
    redirect('minhas_salas.php');
}

// Get sala details
$stmt = $pdo->prepare("
    SELECT s.*, u.nome_completo as organizador_nome,
           p.nome as provincia_nome,
           (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
    FROM salas_eleitorais s
    LEFT JOIN provincias p ON s.provincia_origem = p.id
    JOIN users u ON s.organizador_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$salaId]);
$sala = $stmt->fetch();

if (!$sala) {
    setFlash('error', 'Sala nao encontrada.');
    redirect('minhas_salas.php');
}

$totalVotos = (int)$sala['total_votos'];

// --- Time-based Result Logic ---
$now = time();
$dataFim = !empty($sala['data_fim']) ? strtotime($sala['data_fim']) : 0;
$isFinished = ($dataFim > 0 && $now >= $dataFim);
$isFinalized = ($sala['estado'] === 'finalizada');
$isOfficial = ($isFinished || $isFinalized);

// Get all themes - using tipo_votacao column (not 'tipo')
$stmt = $pdo->prepare("SELECT * FROM temas WHERE sala_id = ? ORDER BY ordem ASC");
$stmt->execute([$salaId]);
$temas = $stmt->fetchAll();

// Get all candidates with vote counts
$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM votos WHERE candidato_id = c.id) as total_votos_candidato
    FROM candidatos c
    WHERE c.sala_id = ?
    ORDER BY votos_totais DESC, c.nome ASC
");
$stmt->execute([$salaId]);
$candidatos = $stmt->fetchAll();

// Vote results per theme
$temaResults = [];
foreach ($temas as $tema) {
    // For themes with candidates - get votes per candidate
    if (!empty($tema['tipo_votacao']) && $tema['tipo_votacao'] !== 'sim_nao') {
        // Get per-candidate votes for this theme
        $stmt = $pdo->prepare("
            SELECT c.id, c.nome, c.partido, c.slogan,
                   COALESCE(SUM(CASE WHEN v.candidato_id = c.id THEN 1 ELSE 0 END), 0) as num_votos
            FROM candidatos c
            LEFT JOIN votos v ON v.candidato_id = c.id AND v.tema_id = ?
            WHERE c.tema_id = ?
            GROUP BY c.id
            ORDER BY num_votos DESC
        ");
        $stmt->execute([$tema['id'], $tema['id']]);
        $cResults = $stmt->fetchAll();
        $temaResults[$tema['id']]['candidatos'] = $cResults;

        $temaResults[$tema['id']]['total_votos'] = array_sum(array_column($cResults, 'num_votos'));

        // Calculate percentages
        $totalT = $temaResults[$tema['id']]['total_votos'];
        foreach ($temaResults[$tema['id']]['candidatos'] as &$rc) {
            $rc['percentagem'] = $totalT > 0 ? round(($rc['num_votos'] / $totalT) * 100, 1) : 0;
        }
        unset($rc);

    } else {
        // Sim/Nao results
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN opcao_sim_nao = 'sim' THEN 1 ELSE 0 END), 0) as sim,
                COALESCE(SUM(CASE WHEN opcao_sim_nao = 'nao' THEN 1 ELSE 0 END), 0) as nao,
                COUNT(*) as total
            FROM votos WHERE tema_id = ?
        ");
        $stmt->execute([$tema['id']]);
        $temaResults[$tema['id']] = $stmt->fetch();
    }
}

// Find winner among candidates
$winner = null;
$maxVotes = 0;
foreach ($candidatos as $c) {
    $cv = (int)$c['votos_totais'];
    if ($cv > $maxVotes) {
        $maxVotes = $cv;
        $winner = $c;
    }
}

// Find sim/nao winners
$temaWinners = [];
foreach ($temas as $tema) {
    if (isset($temaResults[$tema['id']]['sim'])) {
        $r = $temaResults[$tema['id']];
        $temaWinners[$tema['id']] = ((int)$r['sim'] >= (int)$r['nao']) ? 'Sim' : 'Nao';
    }
}

$pageTitle = 'Resultados';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <!-- Results Header -->
    <div class="results-header">
        <div>
            <h2 style="margin-bottom: 0.5rem;">&#128202; Resultados - <?= htmlspecialchars($sala['nome']) ?></h2>
            <p>
                <span class="badge badge-<?= htmlspecialchars(strtolower($sala['estado'])) ?>"><?= htmlspecialchars(ucfirst($sala['estado'])) ?></span>
                &middot; Total de Votos: <strong><?= number_format($totalVotos) ?></strong>
                &middot; Organizador: <?= htmlspecialchars($sala['organizador_nome']) ?>
                <?php if ($sala['provincia_nome']): ?>
                    &middot; &#128205; <?= htmlspecialchars($sala['provincia_nome']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="results-actions">
            <a href="sala_detalhes.php?id=<?= (int)$sala['id'] ?>" class="btn btn-ghost btn-sm">&#8592; Voltar</a>
            <a href="votar.php?sala=<?= (int)$sala['id'] ?>" class="btn btn-success btn-sm">&#128499; Votar</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">&#128424; Imprimir</button>
        </div>
    </div>

    <?php if (!$isOfficial): ?>
    <div class="alert alert-info" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; background: rgba(59, 130, 246, 0.1); border: 1px solid var(--blue); color: var(--blue-deeper);">
        <div style="font-size: 1.5rem;">🕒</div>
        <div>
            <strong style="display: block;">Apuramento em curso</strong>
            <span style="font-size: 0.9rem;">Estas estatísticas são provisórias. O veredito oficial será emitido após <strong><?= formatDate($sala['data_fim']) ?></strong>.</span>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($temas)): ?>
    <div class="empty-state">
        <span class="empty-icon">&#128202;</span>
        <p>Ainda nao ha temas configurados nesta sala.</p>
    </div>
    <?php else: ?>

    <?php foreach ($temas as $tema): ?>
    <div class="result-section">
        <h3><?= htmlspecialchars($tema['titulo']) ?></h3>
        <?php if (!empty($tema['descricao'])): ?>
        <p class="tema-desc"><?= htmlspecialchars($tema['descricao']) ?></p>
        <?php endif; ?>

        <p style="color:var(--gray-500);font-size:0.85rem;margin-bottom:0.75rem;">
            Tipo: <span class="badge badge-info"><?= htmlspecialchars($tema['tipo_votacao']) ?></span>
            &nbsp;|&nbsp; Votos neste tema: <strong><?= isset($temaResults[$tema['id']]['total']) ? $temaResults[$tema['id']]['total'] : (isset($temaResults[$tema['id']]['total_votos']) ? $temaResults[$tema['id']]['total_votos'] : 0) ?></strong>
        </p>

        <!-- Sim/Nao Results -->
        <?php if (isset($temaResults[$tema['id']]['sim'])): ?>
        <?php
        $r = $temaResults[$tema['id']];
        $simCount = (int)$r['sim'];
        $naoCount = (int)$r['nao'];
        $totalTema = (int)$r['total'];
        $simPct = $totalTema > 0 ? round(($simCount / $totalTema) * 100, 1) : 0;
        $naoPct = $totalTema > 0 ? round(($naoCount / $totalTema) * 100, 1) : 0;
        ?>

        <?php if (isset($temaWinners[$tema['id']])): ?>
        <div class="winner-banner" style="<?= !$isOfficial ? 'background: var(--gray-100); border-left: 4px solid var(--gray-400);' : '' ?>">
            <span><?= $isOfficial ? ($temaWinners[$tema['id']] === 'Sim' ? '✅' : '❌') : '📊' ?></span>
            <span><?= $isOfficial ? 'Resultado:' : 'Tendência:' ?> <strong><?= htmlspecialchars($temaWinners[$tema['id']]) ?></strong></span>
            <span style="color:var(--gray-600);font-size:0.9rem;">(<?= $simPct ?>% sim vs <?= $naoPct ?>% nao)</span>
            </div>
        <?php endif; ?>

        <div class="chart-bar-container">
            <?php if ($totalTema > 0): ?>
            <div class="chart-bar bar-sim" style="width: <?= max($simPct, 0) ?>%;"><?= $simPct > 10 ? $simPct . '%' : '' ?></div>
            <div class="chart-bar bar-nao" style="width: <?= max($naoPct, 0) ?>%;"><?= $naoPct > 10 ? $naoPct . '%' : '' ?></div>
            <?php else: ?>
            <div style="width: 100%; display: flex; align-items: center; justify-content: center; color: var(--gray-400); font-size: 0.85rem;">
                Sem votos neste tema
            </div>
            <?php endif; ?>
        </div>
        <div class="chart-total">Sim: <?= $simCount ?> (<?= $simPct ?>%) | Nao: <?= $naoCount ?> (<?= $naoPct ?>%) | Total: <?= $totalTema ?></div>

        <!-- Candidate Results -->
        <?php elseif (isset($temaResults[$tema['id']]['candidatos']) && !empty($temaResults[$tema['id']]['candidatos'])): ?>
        <?php
        $cResults = $temaResults[$tema['id']]['candidatos'];
        $cTemaWinner = ($cResults[0]['num_votos'] > 0) ? $cResults[0] : null;
        ?>

        <?php if ($cTemaWinner): ?>
        <div class="winner-banner" style="<?= !$isOfficial ? 'background: var(--blue-soft); border-left: 4px solid var(--blue); color: var(--blue-deeper);' : '' ?>">
            <span><?= $isOfficial ? '🏆' : '📈' ?></span>
            <span><?= $isOfficial ? 'Vencedor:' : 'Liderança Atual:' ?> <strong><?= htmlspecialchars($cTemaWinner['nome']) ?></strong></span>
            <?php if ($cTemaWinner['partido']): ?>
            <span class="partido-tag"><?= htmlspecialchars($cTemaWinner['partido']) ?></span>
            <?php endif; ?>
            <span class="winner-votes">- <?= $cTemaWinner['num_votos'] ?> votos (<?= $cTemaWinner['percentagem'] ?>%)</span>
            </div>
        <?php endif; ?>

        <?php foreach ($cResults as $c): ?>
        <div class="candidate-result">
            <div class="candidate-result-info">
                <div class="candidate-avatar"><?= strtoupper(substr($c['nome'], 0, 1)) ?></div>
                <div style="flex:1;">
                    <strong style="font-size: 1rem;"><?= htmlspecialchars($c['nome']) ?></strong>
                    <?php if ($c['partido']): ?>
                    <span class="partido-tag"><?= htmlspecialchars($c['partido']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <strong style="font-size: 1.1rem;"><?= (int)$c['num_votos'] ?></strong>
                    <span style="color:var(--gray-500);font-size:0.8rem;"> votos</span>
                    <span style="color:var(--blue);font-weight:600;font-size:0.85rem;"><?= $c['percentagem'] ?>%</span>
                </div>
            </div>
            <?php if ($temaResults[$tema['id']]['total_votos'] > 0): ?>
            <div class="vote-bar">
                <div class="vote-bar-fill" style="width: <?= max($c['percentagem'], 2) ?>%;">
                    <?= $c['percentagem'] ?>%
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <p style="color:var(--gray-400);">Nenhum voto registado ainda.</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Overall Candidate Summary -->
    <?php if (!empty($candidatos)): ?>
    <div class="result-section">
        <h3>&#128101; Resumo Geral de Candidatos</h3>

        <?php if ($winner && $maxVotes > 0): ?>
        <div class="winner-banner" style="<?= !$isOfficial ? 'background: var(--blue-soft); border-left: 4px solid var(--blue); color: var(--blue-deeper);' : '' ?>">
            <span style="font-size: 1.5rem;"><?= $isOfficial ? '🏆' : '🔥' ?></span>
            <div>
                <strong><?= $isOfficial ? 'Vencedor Geral:' : 'Favorito Atual:' ?> <?= htmlspecialchars($winner['nome']) ?></strong>
                <?php if (!empty($winner['partido'])): ?>
                    <span class="partido-tag"><?= htmlspecialchars($winner['partido']) ?></span>
                <?php endif; ?>
                <div class="winner-votes"><?= number_format($maxVotes) ?> votos acumulados</div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $colors = [
            'linear-gradient(90deg, #3b82f6, #60a5fa)',
            'linear-gradient(90deg, #8b5cf6, #a78bfa)',
            'linear-gradient(90deg, #10b981, #34d399)',
            'linear-gradient(90deg, #f59e0b, #fbbf24)',
            'linear-gradient(90deg, #ef4444, #f87171)',
            'linear-gradient(90deg, #ec4899, #f472b6)',
            'linear-gradient(90deg, #06b6d4, #22d3ee)',
            'linear-gradient(90deg, #84cc16, #a3e635)',
        ];
        ?>

        <?php if ($totalVotos > 0): ?>
        <div class="chart-bar-container" style="height: 56px;">
            <?php foreach ($candidatos as $i => $cand): ?>
            <?php $pct = $totalVotos > 0 ? round(((int)$cand['votos_totais'] / $totalVotos) * 100, 1) : 0; ?>
            <?php if ($pct > 0): ?>
            <div class="chart-bar" style="width: <?= $pct ?>%; background: <?= $colors[$i % count($colors)] ?>;" title="<?= htmlspecialchars($cand['nome']) ?>: <?= $pct ?>%">
                <?= $pct > 8 ? $pct . '%' : '' ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>
