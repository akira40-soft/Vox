<?php
/**
 * votar_publico.php - Vox Electoral Platform
 * Feed of public electoral rooms for voters.
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$stmt = $pdo->prepare("
    SELECT s.*, p.nome as provincia_nome, u.nome_completo as organizador_nome,
           (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
    FROM salas_eleitorais s
    LEFT JOIN provincias p ON s.provincia_origem = p.id
    LEFT JOIN users u ON s.organizador_id = u.id
    WHERE s.visibilidade = 'publica' AND s.estado = 'ativa'
    ORDER BY s.criado_em DESC
");
$stmt->execute();
$publicSalas = $stmt->fetchAll();

$pageTitle = 'Eleições Públicas';
require 'includes/header.php';
?>

<div class="dashboard-content">
    <div style="margin-bottom: 3rem;">
        <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -1.5px;">Votações Públicas</h1>
        <p style="color: var(--gray-500); font-size: 1.1rem;">Participe nas decisões que moldam a nossa comunidade. Estas salas estão abertas a todos os cidadãos.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem;">
        <?php foreach ($publicSalas as $sala): ?>
            <div class="card" style="padding: 0; border-radius: 1.5rem; overflow: hidden; border: 1px solid var(--gray-200); background: white; transition: 0.3s; display: flex; flex-direction: column;">
                <div style="padding: 2rem; flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span style="font-size: 0.75rem; background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 0.35rem 0.75rem; border-radius: 2rem; font-weight: 800; text-transform: uppercase;"><?= htmlspecialchars($sala['provincia_nome'] ?? 'Nacional') ?></span>
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--success);"><?= $sala['total_votos'] ?> Participantes</div>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.75rem;"><?= htmlspecialchars($sala['nome']) ?></h3>
                    <p style="color: var(--gray-500); font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem;"><?= htmlspecialchars(substr($sala['descricao'] ?? '', 0, 120)) ?>...</p>
                    
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: auto;">
                        <div style="width: 32px; height: 32px; background: var(--gray-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 800;"><?= strtoupper(substr($sala['organizador_nome'], 0, 1)) ?></div>
                        <span style="font-size: 0.85rem; color: var(--gray-600); font-weight: 600;"><?= htmlspecialchars($sala['organizador_nome']) ?></span>
                    </div>
                </div>
                <div style="padding: 1.25rem 2rem; background: var(--gray-50); border-top: 1px solid var(--gray-100);">
                    <a href="sala_detalhes.php?id=<?= $sala['id'] ?>" class="btn" style="display: block; width: 100%; text-align: center; background: var(--primary); color: white; font-weight: 700; padding: 0.75rem; border-radius: 0.75rem;">Aceder e Votar</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($publicSalas)): ?>
            <div style="grid-column: 1/-1; padding: 5rem; text-align: center; background: white; border-radius: 2rem; border: 2px dashed var(--gray-200);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                <h3 style="font-weight: 800; color: var(--gray-400);">Nenhuma sala pública ativa no momento.</h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
</style>

<?php require 'includes/footer.php'; ?>
