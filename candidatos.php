<?php
/**
 * candidatos.php - Vox Electoral Platform
 * Overhauled Candidates listing with Premium Design
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$userRole = $_SESSION['user_role'] ?? 'eleitor';

// Filter by sala
$salaFilter = (int)($_GET['sala_id'] ?? 0);

$where = "WHERE c.estado = 'ativo'";
$params = [];

if ($salaFilter > 0) {
    $where .= " AND c.sala_id = ?";
    $params[] = $salaFilter;
}

// Fetch active rooms for filter
$stmt = $pdo->query("SELECT id, nome FROM salas_eleitorais WHERE estado = 'ativa' ORDER BY nome");
$salasList = $stmt->fetchAll();

// Fetch candidates with richer info
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, c.partido, c.slogan, c.biografia, c.votos_totais, c.foto,
           s.nome as sala_nome, s.id as sala_id, s.codigo_acesso,
           p.nome as provincia_nome,
           (SELECT COUNT(*) FROM votos WHERE candidato_id = c.id) as real_votos
    FROM candidatos c
    JOIN salas_eleitorais s ON c.sala_id = s.id
    LEFT JOIN provincias p ON s.provincia_origem = p.id
    $where
    ORDER BY real_votos DESC, c.nome ASC
    LIMIT 60
");
$stmt->execute($params);
$candidatos = $stmt->fetchAll();

$pageTitle = 'Candidatos Oficiais';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <!-- Premium Banner -->
    <div class="hub-hero" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 3.5rem; border-radius: 2rem; color: white; margin-bottom: 3rem; box-shadow: var(--shadow-xl); position: relative; overflow: hidden;">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap;">
            <div style="max-width: 600px;">
                <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -1.5px; margin-bottom: 0.75rem;">Candidatos Oficiais</h1>
                <p style="font-size: 1.1rem; opacity: 0.8; line-height: 1.6;">Explore os perfis dos cidadãos que colocaram o seu nome à disposição do processo democrático na Vox.</p>
            </div>
            <a href="home.php" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 0.75rem 1.5rem; border-radius: 1rem; font-weight: 700; backdrop-filter: blur(10px);">← Voltar ao Início</a>
        </div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: var(--primary); opacity: 0.1; filter: blur(40px); border-radius: 50%;"></div>
    </div>

    <!-- Toolbar & Filters -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1.5rem; background: var(--bg-card); padding: 1.25rem 2rem; border-radius: 1.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <form method="GET" style="display: flex; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Filtrar Sala:</span>
                <select name="sala_id" onchange="this.form.submit()" style="padding: 0.65rem 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border-color); background: var(--bg-body); font-weight: 700; color: var(--text-main); cursor: pointer; outline: none;">
                    <option value="">Todas as Eleições</option>
                    <?php foreach ($salasList as $sala): ?>
                        <option value="<?= $sala['id'] ?>" <?= $salaFilter === (int)$sala['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sala['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted);"><?= count($candidatos) ?> candidatos ativos</span>
            <div style="height: 20px; width: 1px; background: var(--border-color);"></div>
            <button onclick="window.print()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.5; transition: 0.3s;" title="Imprimir Lista">🖨️</button>
        </div>
    </div>

    <?php if (!empty($candidatos)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem;">
            <?php foreach ($candidatos as $c): ?>
                <div class="card-candidate" style="background: var(--bg-card); border-radius: 1.5rem; border: 1px solid var(--border-color); position: relative; transition: 0.4s; overflow: hidden; display: flex; flex-direction: column;">
                    
                    <!-- Card Top -->
                    <div style="padding: 2rem; flex: 1;">
                        <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div style="width: 70px; height: 70px; background: linear-gradient(135deg, var(--bg-body), var(--border-color)); border-radius: 1.25rem; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 900; color: var(--text-muted); border: 2px solid var(--bg-card); box-shadow: var(--shadow-sm);">
                                <?= strtoupper(substr($c['nome'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-header); margin-bottom: 0.25rem;"><?= htmlspecialchars($c['nome']) ?></h3>
                                <span style="font-size: 0.75rem; background: var(--blue-light); color: var(--primary); padding: 0.25rem 0.75rem; border-radius: 2rem; font-weight: 800; text-transform: uppercase;">
                                    <?= htmlspecialchars($c['partido'] ?: 'Independente') ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($c['slogan']): ?>
                            <p style="font-size: 0.9rem; color: var(--text-muted); font-style: italic; margin-bottom: 1rem; padding-left: 0.75rem; border-left: 3px solid var(--border-color);">
                                &quot;<?= htmlspecialchars($c['slogan']) ?>&quot;
                            </p>
                        <?php endif; ?>

                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                                <span style="font-size: 1rem;">🗳️</span>
                                <strong><?= htmlspecialchars($c['sala_nome']) ?></strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 1rem;">📍</span>
                                <span><?= htmlspecialchars($c['provincia_nome'] ?: 'Nacional') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body / Stats -->
                    <div style="background: var(--bg-body); padding: 1.25rem 2rem; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 900; color: var(--text-header);"><?= (int)$c['real_votos'] ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Votos Obtidos</div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="votar.php?sala=<?= (int)$c['sala_id'] ?>" class="btn-action" title="Votar nesta Eleição">🗳️</a>
                            <a href="resultados.php?sala=<?= (int)$c['sala_id'] ?>" class="btn-action" title="Ver Resultados">📊</a>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 6rem; background: var(--bg-card); border-radius: 2rem; border: 2px dashed var(--border-color);">
            <div style="font-size: 4rem; opacity: 0.2; margin-bottom: 1rem;">👤</div>
            <h3 style="font-size: 1.5rem; font-weight: 800; color: var(--text-muted);">Nenhum candidato ativo encontrado.</h3>
            <?php if ($salaFilter > 0): ?>
                <a href="candidatos.php" style="color: var(--primary); font-weight: 700; margin-top: 1rem; display: inline-block;">Ver todas as listas</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<style>
    .card-candidate:hover {
        transform: translateY(-8px);
        border-color: var(--primary);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .btn-action {
        width: 40px;
        height: 40px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        text-decoration: none;
        transition: 0.3s;
        font-size: 1rem;
    }
    .btn-action:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: scale(1.1);
    }
</style>

<?php require 'includes/footer.php'; ?>
