<?php
require_once 'config/helpers.php';
$userId = requireAuth();

// Filter by estado and tipo
$estadoFilter = $_GET['estado'] ?? '';
$tipoFilter = $_GET['tipo'] ?? '';

$sql = "
    SELECT s.*, p.nome as provincia_nome,
           (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos,
           (SELECT COUNT(*) FROM candidatos WHERE sala_id = s.id) as total_candidatos,
           (SELECT COUNT(*) FROM temas WHERE sala_id = s.id) as total_temas,
           (SELECT COUNT(*) FROM convites_sala WHERE sala_id = s.id) as total_convites
    FROM salas_eleitorais s
    LEFT JOIN provincias p ON s.provincia_origem = p.id
    WHERE (s.organizador_id = :userId 
       OR EXISTS (SELECT 1 FROM candidatos c WHERE c.sala_id = s.id AND c.user_id = :userId2))
";

$params = ['userId' => $userId, 'userId2' => $userId];

if ($estadoFilter !== '') {
    $sql .= " AND s.estado = :estado";
    $params['estado'] = $estadoFilter;
}
if ($tipoFilter !== '') {
    $sql .= " AND s.tipo = :tipo";
    $params['tipo'] = $tipoFilter;
}

$sql .= " ORDER BY s.criado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salas = $stmt->fetchAll();

// Auto-sync phases for all rooms shown (autonomous behavior)
foreach ($salas as &$s) {
    syncRoomPhase($pdo, $s['id']);
}

$pageTitle = 'Minhas Salas';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <div class="dashboard-panel" style="padding: 2.5rem; margin-bottom: 2rem; background: var(--blue-dark); color: white; border: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: white;">Minhas Salas Eleitorais</h2>
                <p style="opacity: 0.8; font-size: 1.1rem;">Gira as tuas eleições, assembleias e consultas democráticas em curso.</p>
            </div>
            <div class="top-bar-actions">
                <a href="criar_sala.php" class="btn btn-primary" style="background: white; color: var(--blue-deeper); border: none; font-weight: 800; padding: 0.75rem 1.5rem;">+ Nova Sala</a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <!-- Filters -->
    <div class="dashboard-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
        <form class="filters-form" method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">Estado</label>
                <select name="estado" style="padding: 0.5rem 1rem; border-radius: 8px;">
                    <option value="">Todos os Estados</option>
                    <option value="ativa" <?= $estadoFilter === 'ativa' ? 'selected' : '' ?>>🟢 Ativa</option>
                    <option value="rascunho" <?= $estadoFilter === 'rascunho' ? 'selected' : '' ?>>📝 Rascunho</option>
                    <option value="finalizada" <?= $estadoFilter === 'finalizada' ? 'selected' : '' ?>>🏁 Finalizada</option>
                    <option value="cancelada" <?= $estadoFilter === 'cancelada' ? 'selected' : '' ?>>❌ Cancelada</option>
                    <option value="pausada" <?= $estadoFilter === 'pausada' ? 'selected' : '' ?>>⏸️ Pausada</option>
                </select>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase;">Tipo de Sala</label>
                <select name="tipo" style="padding: 0.5rem 1rem; border-radius: 8px;">
                    <option value="">Todos os Tipos</option>
                    <option value="nacional" <?= $tipoFilter === 'nacional' ? 'selected' : '' ?>>🌍 Nacional</option>
                    <option value="municipal" <?= $tipoFilter === 'municipal' ? 'selected' : '' ?>>🏙️ Municipal</option>
                    <option value="comunitario" <?= $tipoFilter === 'comunitario' ? 'selected' : '' ?>>🤝 Comunitário</option>
                    <option value="privado" <?= $tipoFilter === 'privado' ? 'selected' : '' ?>>🔒 Privado</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: flex-end; height: 100%; margin-top: 1.25rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem;">Aplicar Filtros</button>
                <?php if ($estadoFilter || $tipoFilter): ?>
                    <a href="minhas_salas.php" class="btn btn-ghost" style="color: var(--red);">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (!empty($salas)): ?>
    <div class="salas-grid">
        <?php foreach ($salas as $sala): ?>
        <div class="sala-card">
            <div class="sala-card-header">
                <h3><?= htmlspecialchars($sala['nome']) ?></h3>
                <span class="badge badge-<?= htmlspecialchars(strtolower($sala['estado'])) ?>"><?= htmlspecialchars(ucfirst($sala['estado'])) ?></span>
            </div>

            <?php if (!empty($sala['descricao'])): ?>
            <p class="sala-desc"><?= htmlspecialchars(substr($sala['descricao'], 0, 120)) ?><?= strlen($sala['descricao']) > 120 ? '...' : '' ?></p>
            <?php endif; ?>

            <div class="sala-card-stats">
                <span>&#128499; <?= (int)$sala['total_votos'] ?> votos</span>
                <span>&#128101; <?= (int)$sala['total_candidatos'] ?> candidatos</span>
                <span>&#128203; <?= (int)$sala['total_temas'] ?> temas</span>
            </div>

            <?php if ($sala['provincia_nome']): ?>
            <div style="font-size: 0.85rem; color: var(--gray-500);">
                &#128205; <?= htmlspecialchars($sala['provincia_nome']) ?>
            </div>
            <?php endif; ?>

            <div class="sala-card-footer">
                <span class="badge badge-info"><?= htmlspecialchars(ucfirst($sala['tipo'])) ?></span>
                <span style="font-size: 0.8rem; color: var(--gray-500);">
                    Codigo: <code style="background: var(--gray-100); padding: 0.1rem 0.4rem; border-radius: 4px;"><?= htmlspecialchars($sala['codigo_acesso']) ?></code>
                </span>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-400);">
                Criada em <?= formatDate($sala['criado_em']) ?>
            </div>

            <div class="sala-card-actions">
                <a href="sala_detalhes.php?id=<?= (int)$sala['id'] ?>" class="btn btn-primary btn-sm">Detalhes</a>
                <a href="gerir_sala.php?id=<?= (int)$sala['id'] ?>" class="btn btn-ghost btn-sm">Gerir</a>
                <a href="resultados.php?sala=<?= (int)$sala['id'] ?>" class="btn btn-ghost btn-sm">Resultados</a>
                <a href="votar.php?sala=<?= (int)$sala['id'] ?>" class="btn btn-success btn-sm">Votar</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="empty-icon">&#128221;</span>
        <p>Nenhuma sala encontrada.</p>
        <p style="font-size: 0.9rem; color: var(--gray-400);">Crie sua primeira sala eleitoral para comecar!</p>
        <br>
        <a href="criar_sala.php" class="btn btn-primary">&#43; Criar Sala</a>
    </div>
    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>
