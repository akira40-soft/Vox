<?php
require_once 'config/helpers.php';
$userId = requireAuth();

$userRole = $_SESSION['user_role'] ?? 'eleitor';
$userName = $_SESSION['user_nome'] ?? 'Utilizador';

// Fetch recent active elections for everyone (only Public ones for voters)
if ($userRole === 'organizador') {
    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome,
               (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE s.organizador_id = ?
        ORDER BY s.criado_em DESC
        LIMIT 4
    ");
    $stmt->execute([$userId]);
    $recentElections = $stmt->fetchAll();
} else {
    // For VOTERS: Show public rooms + private rooms where they are candidates/members
    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome,
               (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE (s.visibilidade = 'publica' 
           OR s.organizador_id = :userId 
           OR EXISTS (SELECT 1 FROM candidatos c WHERE c.sala_id = s.id AND c.user_id = :userId2))
        AND s.estado IN ('ativa', 'campanha', 'rascunho')
        ORDER BY s.criado_em DESC
        LIMIT 6
    ");
    $stmt->execute(['userId' => $userId, 'userId2' => $userId]);
    $recentElections = $stmt->fetchAll();
    
    // Fetch rooms where voter participated
    $stmt = $pdo->prepare("
        SELECT s.*, v.criado_em as data_voto
        FROM votos v
        JOIN salas_eleitorais s ON v.sala_id = s.id
        WHERE v.user_id = ?
        GROUP BY s.id
        ORDER BY v.criado_em DESC
        LIMIT 4
    ");
    $stmt->execute([$userId]);
    $myParticipation = $stmt->fetchAll();
}

// Auto-sync phases for autonomous behavior
if (!empty($recentElections)) {
    foreach ($recentElections as $re) syncRoomPhase($pdo, $re['id']);
}
if (!empty($myParticipation)) {
    foreach ($myParticipation as $mp) syncRoomPhase($pdo, $mp['id']);
}

$pageTitle = 'Página Inicial';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <!-- Hub Hero -->
    <div class="hub-hero ve-card" style="background: linear-gradient(135deg, var(--blue-deeper) 0%, var(--purple) 100%); color: white; margin-bottom: 3rem; position: relative; overflow: hidden; border: none;">
        <div style="position: relative; z-index: 2;">
            <div class="ve-badge" style="background: rgba(255,255,255,0.2); color: white;">Bem-vindo ao Vox Hub</div>
            <h1 style="font-size: 2.75rem; font-weight: 900; letter-spacing: -2px; margin-bottom: 0.75rem;">Olá, <?= htmlspecialchars($userName) ?>!</h1>
            <p style="font-size: 1.15rem; opacity: 0.9; max-width: 600px; color: rgba(255,255,255,0.85);">
                <?php if ($userRole === 'organizador'): ?>
                    Pronto para liderar a próxima jornada democrática? Gerencie suas salas ou crie uma nova eleição agora.
                <?php else: ?>
                    Sua voz é o motor da mudança. Participe nas eleições disponíveis ou entre em uma sala privada com seu código.
                <?php endif; ?>
            </p>
        </div>
        <div style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%; z-index: 1;"></div>
    </div>

    <!-- Private Room Joiner (all roles) -->
    <div class="ve-card" style="margin-bottom: 3rem; display: flex; align-items: center; justify-content: space-between; gap: 2rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px;">
            <h3 class="ve-title" style="margin-bottom: 0.5rem;">Entrar em Sala Privada</h3>
            <p style="color: var(--text-muted);">Tem um código de convite? Insira-o abaixo para aceder à votação exclusiva.</p>
        </div>
        <form action="sala_detalhes.php" method="GET" style="display: flex; gap: 1rem; flex: 1; min-width: 300px;">
            <input type="text" name="code" placeholder="VOX-XXXX" class="input-modern" style="flex: 1; padding: 1.25rem; border-radius: 1rem; border: 2px solid var(--border-color); background: var(--bg-body); color: var(--text-main); font-weight: 700; font-family: 'Courier New', monospace; font-size: 1.1rem;" required>
            <button type="submit" class="btn btn-primary" style="padding: 0 2rem; border-radius: 1rem;">Aceder</button>
        </form>
    </div>

    <!-- Action Cards Grid -->
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-bottom: 4rem;">
        
            <a href="criar_sala.php" class="action-card" style="text-decoration: none; background: var(--bg-card); padding: 2.5rem; border-radius: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: 0.3s; display: block;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">➕</div>
                <h3 style="font-weight: 800; color: var(--text-header); margin-bottom: 0.5rem;">Criar Sala</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Configure uma nova eleição em minutos com nosso assistente guiado.</p>
            </a>
            
            <a href="minhas_salas.php" class="action-card" style="text-decoration: none; background: var(--bg-card); padding: 2.5rem; border-radius: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: 0.3s; display: block;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📂</div>
                <h3 style="font-weight: 800; color: var(--text-header); margin-bottom: 0.5rem;">Minhas Salas</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Gerencie candidatos, convites e acompanhe o progresso das suas eleições.</p>
            </a>

            <a href="votar_publico.php" class="action-card" style="text-decoration: none; background: var(--bg-card); padding: 2.5rem; border-radius: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: 0.3s; display: block;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🗳️</div>
                <h3 style="font-weight: 800; color: var(--text-header); margin-bottom: 0.5rem;">Salas Públicas</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Explore eleições abertas à comunidade e exerça o seu direito de voto.</p>
            </a>

            <a href="minhas_votacoes.php" class="action-card" style="text-decoration: none; background: var(--bg-card); padding: 2.5rem; border-radius: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: 0.3s; display: block;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                <h3 style="font-weight: 800; color: var(--text-header); margin-bottom: 0.5rem;">Minha Atividade</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Consulte o histórico de salas onde participou e seus comprovativos.</p>
            </a>

            <a href="perfil.php" class="action-card" style="text-decoration: none; background: var(--bg-card); padding: 2.5rem; border-radius: 1.5rem; text-align: center; border: 1px solid var(--border-color); transition: 0.3s; display: block;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">👤</div>
                <h3 style="font-weight: 800; color: var(--text-header); margin-bottom: 0.5rem;">Perfil & Segurança</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Gestione seus dados e verifique a integridade da sua identidade digital.</p>
            </a>

    </div>

    <!-- Active Elections / Feed -->
    <div style="margin-bottom: 4rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-size: 1.75rem; font-weight: 900; letter-spacing: -1px;"><?= $userRole === 'organizador' ? 'Salas Recentes' : 'Eleições em Destaque' ?></h2>
            <a href="<?= $userRole === 'organizador' ? 'minhas_salas.php' : 'votar_publico.php' ?>" style="text-decoration: none; color: var(--primary); font-weight: 700; font-size: 0.95rem;">Ver Todas as Salas →</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
            <?php foreach ($recentElections as $sala): ?>
                <div class="ve-card" style="padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <span class="badge" style="background: var(--blue-light); color: var(--blue-dark);"><?= htmlspecialchars($sala['provincia_nome'] ?? 'Nacional') ?></span>
                        <span style="font-size: 0.8rem; font-weight: 800; color: var(--primary);"><i class="fa fa-ticket"></i> <?= (int)$sala['total_votos'] ?> Votos</span>
                    </div>
                    <h4 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.75rem; color: var(--text-header);"><?= htmlspecialchars($sala['nome']) ?></h4>
                    <p style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 1.5rem; line-height: 1.5;"><?= htmlspecialchars(substr($sala['descricao'] ?? '', 0, 100)) ?>...</p>
                    <a href="sala_detalhes.php?id=<?= $sala['id'] ?>" class="btn btn-ghost" style="display: block; text-align: center; font-weight: 700; border-radius: 0.75rem;">Ver Sala <i class="fa fa-arrow-right"></i></a>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($recentElections)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 4rem; background: var(--bg-card); border: 1px dashed var(--border-color); border-radius: 1.5rem; color: var(--text-muted);">
                    <p>Nenhuma sala encontrada no momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
    .action-card:hover { transform: translateY(-8px); border-color: var(--primary); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    .sala-feed-card:hover { transform: scale(1.02); border-color: var(--primary); }
</style>

<?php require 'includes/footer.php'; ?>
