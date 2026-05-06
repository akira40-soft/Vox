<?php
/**
 * votar.php - Vox Electoral Platform
 * Voting page for electoral rooms
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$erro = null;
$sucesso = null;
$sala = null;
$temas = [];
$voteHash = '';
$salaId = 0;

// Check for code parameter (access code) or sala id
$codeParam = sanitize($_GET['code'] ?? '');
$salaIdParam = (int)($_GET['sala'] ?? 0);

if ($salaIdParam > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome_completo as organizador_nome, p.nome as provincia_nome
        FROM salas_eleitorais s
        JOIN users u ON s.organizador_id = u.id
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$salaIdParam]);
    $sala = $stmt->fetch();
} elseif (!empty($codeParam)) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome_completo as organizador_nome, p.nome as provincia_nome
        FROM salas_eleitorais s
        JOIN users u ON s.organizador_id = u.id
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE s.codigo_acesso = ?
    ");
    $stmt->execute([$codeParam]);
    $sala = $stmt->fetch();
}

if ($sala) {
    $salaId = (int)$sala['id'];

    if ($sala['estado'] !== 'ativa') {
        $erro = 'Esta sala nao esta ativa. O estado atual e: ' . htmlspecialchars($sala['estado']);
    } else {
        // Check dates
        $now = date('Y-m-d H:i:s');
        if ($now < $sala['data_inicio']) {
            $erro = 'Esta sala ainda nao abriu. Abre em ' . formatDate($sala['data_inicio']);
        } elseif ($now > $sala['data_fim']) {
            $erro = 'Esta sala ja encerrou em ' . formatDate($sala['data_fim']);
        } else {
            // Fetch temas with candidatos
            $stmt = $pdo->prepare("
                SELECT t.id, t.titulo, t.descricao, t.tipo_votacao, t.ordem
                FROM temas t
                WHERE t.sala_id = ?
                ORDER BY t.ordem ASC
            ");
            $stmt->execute([$salaId]);
            $temas = $stmt->fetchAll();

            foreach ($temas as &$tema) {
                if ($tema['tipo_votacao'] === 'sim_nao') {
                    continue;
                }
                $stmt = $pdo->prepare("
                    SELECT c.id, c.nome, c.partido, c.slogan, c.biografia
                    FROM candidatos c
                    WHERE c.tema_id = ?
                    ORDER BY c.nome ASC
                ");
                $stmt->execute([$tema['id']]);
                $tema['candidatos'] = $stmt->fetchAll();
            }
            unset($tema);

            // Check if user already voted
            $stmt = $pdo->prepare("SELECT id FROM votos WHERE sala_id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$salaId, $userId]);
            if ($stmt->fetch()) {
                $erro = 'Voce ja votou nesta sala. Nao e permitido votar mais de uma vez.';
            }
        }
    }
} else {
    $erro = 'Sala nao encontrada. Verifique o codigo de acesso.';
}

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erro && $sala && $sala['estado'] === 'ativa') {
    try {
        $pdo->beginTransaction();

        // Generate vote hash
        $rawSecret = $userId . '-' . $salaId . '-' . time() . '-' . VOTE_SECRET . '-' . bin2hex(random_bytes(8));
        $voteHash = hash('sha256', $rawSecret);
        $ipVotante = $_SERVER['REMOTE_ADDR'] ?? '';

        $totalVotes = 0;

        foreach ($temas as $tema) {
            $temaId = (int)$tema['id'];

            // Check already voted on this tema
            $stmt = $pdo->prepare("SELECT id FROM votos WHERE tema_id = ? AND user_id = ? AND sala_id = ?");
            $stmt->execute([$temaId, $userId, $salaId]);
            if ($stmt->fetch()) continue;

            if ($tema['tipo_votacao'] === 'sim_nao') {
                $opcao = sanitize($_POST['sim_nao_' . $temaId] ?? '');
                if (in_array($opcao, ['sim', 'nao'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO votos (sala_id, user_id, tema_id, candidato_id, opcao_sim_nao, voto_hash, ip_votante)
                        VALUES (?, ?, ?, NULL, ?, ?, ?)
                    ");
                    $stmt->execute([$salaId, $userId, $temaId, $opcao, $voteHash, $ipVotante]);
                    $totalVotes++;
                }
            } else {
                // Candidate voting
                $selected = $_POST['candidato_' . $temaId] ?? null;

                if ($tema['tipo_votacao'] === 'multiplo' && is_array($selected)) {
                    foreach ($selected as $candId) {
                        $candId = (int)$candId;
                        $stmt = $pdo->prepare("
                            INSERT INTO votos (sala_id, user_id, tema_id, candidato_id, opcao_sim_nao, voto_hash, ip_votante)
                            VALUES (?, ?, ?, ?, NULL, ?, ?)
                        ");
                        $stmt->execute([$salaId, $userId, $temaId, $candId, $voteHash, $ipVotante]);
                        $totalVotes++;

                        // Update candidate vote count
                        $pdo->prepare("UPDATE candidatos SET votos_totais = votos_totais + 1 WHERE id = ?")->execute([$candId]);
                    }
                } elseif ($selected && (int)$selected > 0) {
                    $candId = (int)$selected;
                    $stmt = $pdo->prepare("
                        INSERT INTO votos (sala_id, user_id, tema_id, candidato_id, opcao_sim_nao, voto_hash, ip_votante)
                        VALUES (?, ?, ?, ?, NULL, ?, ?)
                    ");
                    $stmt->execute([$salaId, $userId, $temaId, $candId, $voteHash, $ipVotante]);
                    $totalVotes++;

                    $pdo->prepare("UPDATE candidatos SET votos_totais = votos_totais + 1 WHERE id = ?")->execute([$candId]);
                }
            }
        }

        if ($totalVotes > 0) {
            // Update global stats using prepared statement
            $updateStatsStmt = $pdo->prepare("UPDATE estatisticas_sistema SET valor = valor + ? WHERE metrica = 'total_votos'");
            $updateStatsStmt->execute([$totalVotes]);

            auditLog($userId, 'voto', "Voto registado na sala {$sala['nome']} (hash: " . substr($voteHash, 0, 12) . "...)");
        }

        $pdo->commit();
        $sucesso = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao registar o voto. Tente novamente.';
        error_log("Voto error: " . $e->getMessage());
    }
}

$pageTitle = 'Votar';
require 'includes/header.php';
?>

<div class="dashboard-content vote-page">

    <?php if ($sucesso): ?>
    <!-- Success State -->
    <div class="vote-success">
        <div class="success-icon-large">&#9989;</div>
        <h2>Voto Registo com Sucesso!</h2>
        <p>O seu voto foi registado na sala <strong><?= htmlspecialchars($sala['nome']) ?></strong>.</p>
        </div>

    <div class="vote-hash-info">
        <strong>Hash do Voto (comprovativo)</strong>
        <br>
        <code><?= htmlspecialchars($voteHash) ?></code>
        <p style="margin-top:1rem; color:var(--gray-500);">Guarde este hash para verificar o seu voto mais tarde.</p>
        </div>

    <div class="vote-success-actions" style="margin-top:1.5rem;">
        <a href="resultados.php?sala=<?= $salaId ?>" class="btn btn-primary">Ver Resultados</a>
        <a href="dashboard.php" class="btn btn-ghost">Painel Principal</a>
        </div>

    <?php elseif ($erro): ?>
    <!-- Error State -->
    <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php if ($salaId > 0): ?>
    <a href="sala_detalhes.php?id=<?= $salaId ?>" class="btn btn-ghost">Voltar a Sala</a>
    <?php endif; ?>
    <?php if (!$sala): ?>
    <a href="dashboard.php" class="btn btn-ghost">Painel Principal</a>
    <?php endif; ?>

    <?php elseif ($sala): ?>
    <!-- Voting Form -->
    <div class="vote-info-bar">
        <h3><?= htmlspecialchars($sala['nome']) ?></h3>
        <span>Organizador: <?= htmlspecialchars($sala['organizador_nome']) ?> | Codigo: <?= htmlspecialchars($sala['codigo_acesso']) ?></span>
        <?php if ($sala['provincia_nome']): ?>
        <br><span>Provincia: <?= htmlspecialchars($sala['provincia_nome']) ?></span>
        <?php endif; ?>
        </div>

    <?php if (!empty($sala['descricao'])): ?>
    <p style="color:var(--gray-600);margin-bottom:1.5rem;"><?= htmlspecialchars($sala['descricao']) ?></p>
    <?php endif; ?>

    <form method="POST" class="voting-form" id="votarForm">

        <?php foreach ($temas as $tema): ?>
        <div class="voting-section">
            <div class="voting-section-header">
                <h3><?= htmlspecialchars($tema['titulo']) ?></h3>
                <span class="badge badge-info"><?= htmlspecialchars($tema['tipo_votacao']) ?></span>
            </div>

            <?php if ($tema['descricao']): ?>
            <p class="tema-desc"><?= htmlspecialchars($tema['descricao']) ?></p>
            <?php endif; ?>

            <?php if ($tema['tipo_votacao'] === 'sim_nao'): ?>
            <div class="sim-nao-options">
                <div class="sim-nao-option">
                    <input type="radio" name="sim_nao_<?= $tema['id'] ?>" id="sim_<?= $tema['id'] ?>" value="sim" required>
                    <label for="sim_<?= $tema['id'] ?>" class="sim-nao-label sim-voto">Sim</label>
                </div>
                <div class="sim-nao-option">
                    <input type="radio" name="sim_nao_<?= $tema['id'] ?>" id="nao_<?= $tema['id'] ?>" value="nao" required>
                    <label for="nao_<?= $tema['id'] ?>" class="sim-nao-label nao-voto">Nao</label>
                </div>
            </div>

            <?php elseif (!empty($tema['candidatos'])): ?>
            <div class="candidate-options">
                <?php foreach ($tema['candidatos'] as $candidato): ?>
                <label class="candidate-option">
                    <input type="<?= $tema['tipo_votacao'] === 'multiplo' ? 'checkbox' : 'radio' ?>"
                           name="candidato_<?= $tema['id'] ?><?= $tema['tipo_votacao'] === 'multiplo' ? '[]' : '' ?>"
                           value="<?= $candidato['id'] ?>"
                           <?= $tema['tipo_votacao'] === 'unico' ? 'required' : '' ?>>
                    <div class="candidate-card">
                        <div class="candidate-avatar"><?= strtoupper(substr($candidato['nome'], 0, 1)) ?></div>
                        <div class="candidate-info">
                            <h4><?= htmlspecialchars($candidato['nome']) ?></h4>
                            <?php if ($candidato['partido']): ?>
                            <span class="partido-tag"><?= htmlspecialchars($candidato['partido']) ?></span>
                            <?php endif; ?>
                            <?php if ($candidato['slogan']): ?>
                            <div style="color:var(--gray-500);font-size:0.8rem;margin-top:0.25rem;font-style:italic;">&quot;<?= htmlspecialchars(substr($candidato['slogan'], 0, 80)) ?>&quot;</div>
                            <?php endif; ?>
                        </div>
                        <span class="candidate-check">&#10003;</span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:var(--gray-500)">Nenhum candidato disponivel para este tema.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="voting-submit">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Tem a certeza que deseja submeter o seu voto? Esta acao nao pode ser desfeita.');">
                Submeter Voto
            </button>
        </div>
    </form>

    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
