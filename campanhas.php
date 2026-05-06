<?php
/**
 * campanhas.php - Vox Electoral Platform
 * Campaign listing with type badges, author info
 */
require_once 'config/helpers.php';
$userId = requireAuth();

// Filter by sala
$salaFilter = (int)($_GET['sala_id'] ?? 0);
$tipoFilter = sanitize($_GET['tipo'] ?? '');

$where = "1=1";
$params = [];

if ($salaFilter > 0) {
    $where .= " AND ca.sala_id = ?";
    $params[] = $salaFilter;
}
if (!empty($tipoFilter) && in_array($tipoFilter, ['proposta', 'comicio', 'debate', 'manifesto'])) {
    $where .= " AND ca.tipo = ?";
    $params[] = $tipoFilter;
}

// Fetch salas for filter dropdown
$stmt = $pdo->query("SELECT id, nome FROM salas_eleitorais WHERE estado = 'ativa' ORDER BY nome");
$salasList = $stmt->fetchAll();

// Fetch campaigns
$stmt = $pdo->prepare("
    SELECT ca.*, c.nome as candidato_nome, c.partido as candidato_partido,
           s.nome as sala_nome, u.nome_completo as autor_nome
    FROM campanhas ca
    JOIN candidatos c ON ca.candidato_id = c.id
    JOIN salas_eleitorais s ON ca.sala_id = s.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE $where
    ORDER BY ca.criado_em DESC
    LIMIT 50
");
$stmt->execute($params);
$campanhas = $stmt->fetchAll();

$pageTitle = 'Campanhas';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <div class="dashboard-panel" style="padding: 2.5rem; margin-bottom: 2rem; background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%); color: white; border: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: white;">Campanhas Eleitorais</h2>
                <p style="opacity: 0.8; font-size: 1.1rem;">Explore as propostas, manifestos e eventos dos candidatos ativos.</p>
            </div>
            <a href="dashboard.php" class="btn btn-primary" style="background: white; color: var(--purple-dark); border: none; font-weight: 800; padding: 0.75rem 1.5rem;">Voltar ao Painel</a>
        </div>
    </div>

    <!-- Filters -->
    <!-- Filters -->
    <div class="dashboard-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
        <div class="filters-form" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <form method="GET" style="display:flex; gap:1rem; align-items: center; flex-wrap:wrap;">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">Filtrar por Sala</label>
                    <select name="sala_id" onchange="this.form.submit()" style="padding: 0.5rem 1rem; border-radius: 8px;">
                        <option value="">Todas as Salas</option>
                        <?php foreach ($salasList as $sala): ?>
                            <option value="<?= $sala['id'] ?>" <?= $salaFilter === (int)$sala['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sala['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <label style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">Tipo de Conteúdo</label>
                    <select name="tipo" onchange="this.form.submit()" style="padding: 0.5rem 1rem; border-radius: 8px;">
                        <option value="">Todos os Tipos</option>
                        <option value="proposta" <?= $tipoFilter === 'proposta' ? 'selected' : '' ?>>📄 Proposta</option>
                        <option value="comicio" <?= $tipoFilter === 'comicio' ? 'selected' : '' ?>>📢 Comício</option>
                        <option value="debate" <?= $tipoFilter === 'debate' ? 'selected' : '' ?>>💬 Debate</option>
                        <option value="manifesto" <?= $tipoFilter === 'manifesto' ? 'selected' : '' ?>>📜 Manifesto</option>
                    </select>
                </div>
            </form>
            <span style="background: var(--purple-light); color: var(--purple-dark); padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700; font-size: 0.85rem;">
                <?= count($campanhas) ?> Publicações Encontradas
            </span>
        </div>
    </div>

    <?php if (!empty($campanhas)): ?>
    <div class="campanhas-grid">
        <?php foreach ($campanhas as $camp): ?>
        <div class="campanha-card">
            <?php if ($camp['imagem']): ?>
            <div class="campanha-imagem">
                <img src="<?= htmlspecialchars($camp['imagem']) ?>" alt="<?= htmlspecialchars($camp['titulo']) ?>" loading="lazy">
            </div>
            <?php endif; ?>

            <div class="campanha-content">
                <div class="campanha-header">
                    <?php
                    $badgeClass = '';
                    switch ($camp['tipo']) {
                        case 'proposta': $badgeClass = 'badge-info'; break;
                        case 'comicio': $badgeClass = 'badge-eleitor'; break;
                        case 'debate': $badgeClass = 'badge-organizador'; break;
                        case 'manifesto': $badgeClass = 'badge-candidato'; break;
                    }
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($camp['tipo'])) ?></span>
                    <span style="color:var(--gray-400);font-size:0.8rem;"><?= formatDate($camp['criado_em']) ?></span>
                </div>

                <h3><?= htmlspecialchars($camp['titulo']) ?></h3>

                <div class="campanha-author">
                    <div class="campanha-author-avatar"><?= strtoupper(substr($camp['candidato_nome'], 0, 1)) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($camp['candidato_nome']) ?></strong>
                        <?php if ($camp['candidato_partido']): ?>
                        <br><span class="partido-tag"><?= htmlspecialchars($camp['candidato_partido']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="campanha-text"><?= nl2br(htmlspecialchars($camp['conteudo'])) ?></p>

                <?php if ($camp['data_evento']): ?>
                <div class="campanha-event">
                    <?php if ($camp['local']): ?>
                    &#128205; <strong><?= htmlspecialchars($camp['local']) ?></strong> &middot;
                    <?php endif; ?>
                    &#128197; <?= formatDate($camp['data_evento']) ?>
                </div>
                <?php endif; ?>

                <div class="campanha-meta">
                    <span>&#128064; <?= number_format((int)$camp['visualizacoes']) ?> visualizacoes</span>
                    <span>&#127968; <?= htmlspecialchars($camp['sala_nome']) ?></span>
                    <span>&#128197; <?= formatDate($camp['criado_em']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="empty-icon">&#128227;</span>
        <p>Nenhuma campanha encontrada</p>
        <?php if ($salaFilter > 0 || !empty($tipoFilter)): ?>
        <a href="campanhas.php" class="btn btn-ghost btn-sm" style="margin-top:1rem;">Ver todas as campanhas</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>
