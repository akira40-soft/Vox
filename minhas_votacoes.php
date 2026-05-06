<?php
/**
 * minhas_votacoes.php - Vox Electoral Platform
 * User's voting history and proofs (hashes).
 */
require_once 'config/helpers.php';
$userId = requireAuth();

// Fetch unique salas where user has voted
$stmt = $pdo->prepare("
    SELECT s.id, s.nome, s.codigo_acesso, s.estado,
           MIN(v.criado_em) as data_voto,
           v.voto_hash
    FROM votos v
    JOIN salas_eleitorais s ON v.sala_id = s.id
    WHERE v.user_id = ?
    GROUP BY s.id
    ORDER BY data_voto DESC
");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Minha Atividade';
require 'includes/header.php';
?>

<div class="dashboard-content">
    <div style="margin-bottom: 3rem;">
        <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -1.5px;">Minha Atividade</h1>
        <p style="color: var(--gray-500); font-size: 1.1rem;">Consulte o histórico das suas participações democráticas e os seus comprovativos digitais.</p>
    </div>

    <?php if (empty($history)): ?>
        <div style="text-align: center; padding: 6rem 2rem; background: white; border-radius: 2rem; border: 2px dashed var(--gray-200);">
            <div style="font-size: 4rem; margin-bottom: 1.5rem;">🗳️</div>
            <h2 style="font-weight: 800; color: var(--gray-900);">Ainda não votou em nenhuma sala.</h2>
            <p style="color: var(--gray-500); margin-top: 0.5rem;">As suas participações aparecerão aqui assim que exercer o seu direito de voto.</p>
            <a href="home.php" class="btn btn-primary" style="margin-top: 2rem;">Explorar Salas</a>
        </div>
    <?php else: ?>
        <div style="display: grid; gap: 1.5rem;">
            <?php foreach ($history as $h): ?>
                <div class="card" style="background: white; border-radius: 1.5rem; padding: 2rem; border: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; gap: 2rem; flex-wrap: wrap; transition: 0.3s;">
                    <div style="flex: 1; min-width: 250px;">
                        <h4 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.25rem;"><?= htmlspecialchars($h['nome']) ?></h4>
                        <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">
                            <span>Cód: <?= htmlspecialchars($h['codigo_acesso']) ?></span>
                            <span>•</span>
                            <span>Votado em: <?= date('d/m/Y H:i', strtotime($h['data_voto'])) ?></span>
                        </div>
                    </div>

                    <div style="flex: 1; min-width: 300px; background: var(--gray-50); padding: 1rem; border-radius: 1rem; border: 1px solid var(--gray-100);">
                        <div style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800; color: var(--gray-400); margin-bottom: 0.25rem;">Hash de Verificação (Comprovativo)</div>
                        <code style="font-size: 0.8rem; word-break: break-all; color: var(--primary); font-weight: 600;"><?= htmlspecialchars($h['voto_hash']) ?></code>
                    </div>

                    <div style="display: flex; gap: 0.75rem;">
                        <a href="sala_detalhes.php?id=<?= $h['id'] ?>" class="btn" style="padding: 0.65rem 1.25rem; border: 1px solid var(--gray-200); font-weight: 700; font-size: 0.85rem; border-radius: 0.75rem;">Ver Sala</a>
                        <a href="resultados.php?sala=<?= $h['id'] ?>" class="btn" style="padding: 0.65rem 1.25rem; border: 1px solid var(--gray-200); font-weight: 700; font-size: 0.85rem; border-radius: 0.75rem;">Ver Resultados</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .card:hover { border-color: var(--primary); transform: translateX(5px); box-shadow: var(--shadow-md); }
</style>

<?php require 'includes/footer.php'; ?>
