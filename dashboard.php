<?php
/**
 * dashboard.php - Vox Data & Analytics
 * Enhanced dashboard with premium design and improved logic.
 */
require_once 'config/helpers.php';
$userId = requireAuth();

// Initialize variables to avoid Undefined Variable errors
$userRole = $_SESSION['user_role'] ?? 'eleitor';
$userName = $_SESSION['user_nome'] ?? 'Utilizador';
$recentActivity = [];
$userSalas = [];
$myCandidatures = [];
$availableSalas = [];
$userSalasCount = 0;
$userVotesReceived = 0;
$userCandidatesCount = 0;
$userVotesCount = 0;
$candidaturesCount = 0;
$totalVotesReceived = 0;

// Fetch Global Stats (Administrative only)
$globalStats = [];
if ($userRole === 'admin') {
    $stmt = $pdo->query("SELECT metrica, valor FROM estatisticas_sistema");
    $globalStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Fetch Recent Activity (Role-based filtering)
if ($userRole === 'admin') {
    // Admins see EVERYTHING
    $stmt = $pdo->query("
        SELECT a.*, u.nome_completo as user_nome
        FROM auditoria a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.criado_em DESC
        LIMIT 10
    ");
    $recentActivity = $stmt->fetchAll();
} else {
    // Others only see THEIR OWN activity for security/privacy
    $stmt = $pdo->prepare("
        SELECT a.*, u.nome_completo as user_nome
        FROM auditoria a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.criado_em DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentActivity = $stmt->fetchAll();
}

// ============================================
// ROLE SPECIFIC DATA
// ============================================

if ($userRole === 'organizador') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM salas_eleitorais WHERE organizador_id = ?");
    $stmt->execute([$userId]);
    $userSalasCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM votos v
        INNER JOIN candidatos c ON v.candidato_id = c.id
        WHERE c.criado_por = ?
    ");
    $stmt->execute([$userId]);
    $userVotesReceived = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidatos WHERE criado_por = ?");
    $stmt->execute([$userId]);
    $userCandidatesCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome,
               (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos,
               (SELECT COUNT(*) FROM candidatos WHERE sala_id = s.id) as total_candidatos
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE s.organizador_id = ?
        ORDER BY s.criado_em DESC
        LIMIT 8
    ");
    $stmt->execute([$userId]);
    $userSalas = $stmt->fetchAll();

} elseif ($userRole === 'candidato') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidatos WHERE user_id = ?");
    $stmt->execute([$userId]);
    $candidaturesCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM votos v
        INNER JOIN candidatos c ON v.candidato_id = c.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$userId]);
    $totalVotesReceived = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT c.*, s.nome as sala_nome,
               (SELECT COUNT(*) FROM votos WHERE candidato_id = c.id) as votos
        FROM candidatos c
        LEFT JOIN salas_eleitorais s ON c.sala_id = s.id
        WHERE c.user_id = ?
        ORDER BY votos DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $myCandidatures = $stmt->fetchAll();

} else {
    $stmt = $pdo->prepare("
        SELECT s.*, p.nome as provincia_nome,
               (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
        FROM salas_eleitorais s
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        WHERE s.estado = 'ativa'
        ORDER BY s.criado_em DESC
        LIMIT 6
    ");
    $stmt->execute();
    $availableSalas = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votos WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userVotesCount = $stmt->fetchColumn();
}

// Fetch Pending Reports (Organizers see reports of their rooms, Admins see all)
$pendingReports = [];
if ($userRole === 'admin') {
    $stmt = $pdo->query("
        SELECT d.*, u.nome_completo as autor_nome, COALESCE(c.nome, 'Post') as target_name 
        FROM denuncias d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN candidatos c ON d.candidato_id = c.id
        WHERE d.estado = 'pendente' 
        ORDER BY d.criado_em DESC LIMIT 5
    ");
    $pendingReports = $stmt->fetchAll();
} elseif ($userRole === 'organizador') {
    $stmt = $pdo->prepare("
        SELECT d.*, u.nome_completo as autor_nome, COALESCE(c.nome, 'Post') as target_name 
        FROM denuncias d 
        JOIN users u ON d.user_id = u.id 
        LEFT JOIN candidatos c ON d.candidato_id = c.id
        WHERE d.estado = 'pendente' 
        AND (d.candidato_id IN (SELECT id FROM candidatos WHERE criado_por = ?) 
             OR d.post_id IN (SELECT id FROM campanhas WHERE sala_id IN (SELECT id FROM salas_eleitorais WHERE organizador_id = ?)))
        ORDER BY d.criado_em DESC LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $pendingReports = $stmt->fetchAll();
}

$pageTitle = 'Painel de Dados';
require 'includes/header.php';
?>

<style>
    /* Premium Dashboard Styles */
    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding-bottom: 5rem;
    }

    .dash-hero {
        background: linear-gradient(135deg, #1e3a8a 0%, #4c1d95 100%);
        padding: 4rem 3rem;
        border-radius: 2rem;
        color: white;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
    }

    .dash-hero h1 { font-size: 2.75rem; font-weight: 900; letter-spacing: -2px; margin-bottom: 0.5rem; }
    .dash-hero p { opacity: 0.8; font-size: 1.1rem; max-width: 600px; }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .stat-box {
        background: var(--bg-card);
        padding: 2rem;
        border-radius: 1.5rem;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        transition: var(--transition);
        box-shadow: var(--shadow-base);
    }

    .stat-box:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); border-color: var(--blue); }

    .stat-icon-circle {
        width: 60px; height: 60px;
        border-radius: 1.25rem;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        background: rgba(59, 130, 246, 0.05);
        color: var(--primary);
    }

    .stat-val { font-size: 1.75rem; font-weight: 900; color: var(--gray-900); display: block; line-height: 1; }
    .stat-lbl { font-size: 0.85rem; color: var(--gray-500); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

    .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; }

    .card { background: var(--bg-card); border-radius: 1.5rem; border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-base); }
    .card-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02); }
    .card-header h2 { font-size: 1.1rem; font-weight: 800; color: var(--text-header); }

    .table-container { overflow-x: auto; }
    .vox-table { width: 100%; border-collapse: collapse; }
    .vox-table th { text-align: left; padding: 1.25rem 2rem; background: rgba(0,0,0,0.03); font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
    .vox-table td { padding: 1.25rem 2rem; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; color: var(--text-main); }

    .badge { padding: 0.35rem 0.75rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .badge-ativa { background: rgba(16, 185, 129, 0.1); color: var(--success); }
    .badge-finalizada { background: var(--gray-100); color: var(--gray-500); }

    .eco-sidebar-card {
        background: var(--gray-900);
        color: white;
        padding: 2.5rem;
        border-radius: 1.5rem;
    }

    @media (max-width: 900px) { .main-grid { grid-template-columns: 1fr; } }
</style>

<div class="dashboard-container">
    
    <div class="dash-hero">
        <a href="home.php" style="color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 700; font-size: 0.85rem; display: inline-block; margin-bottom: 1rem;">← Voltar ao Menu Principal</a>
        <?php if ($userRole === 'admin'): ?>
            <h1>Estatísticas de Votação</h1>
            <p>Monitorização em tempo real da integridade e participação no sistema eleitoral Vox.</p>
        <?php elseif ($userRole === 'organizador'): ?>
            <h1>Gestão de Eleições</h1>
            <p>Resumo de desempenho e atividade das salas sob sua responsabilidade.</p>
        <?php else: ?>
            <h1>Meu Desempenho</h1>
            <p>Acompanhe sua influência e participação nas eleições da Vox.</p>
        <?php endif; ?>
    </div>

    <div class="stats-row">
        <?php if ($userRole === 'organizador'): ?>
            <div class="stat-box">
                <div class="stat-icon-circle">📂</div>
                <div><span class="stat-val counter" data-count="<?= (int)$userSalasCount ?>">0</span><span class="stat-lbl">Salas Criadas</span></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon-circle" style="color: var(--success); background: rgba(16, 185, 129, 0.05);">🗳️</div>
                <div><span class="stat-val counter" data-count="<?= (int)$userVotesReceived ?>">0</span><span class="stat-lbl">Votos Captados</span></div>
            </div>
        <?php else: ?>
            <div class="stat-box">
                <div class="stat-icon-circle">🗳️</div>
                <div><span class="stat-val counter" data-count="<?= (int)($userVotesCount ?: $totalVotesReceived) ?>">0</span><span class="stat-lbl">Meus Votos</span></div>
            </div>
        <?php endif; ?>
        
        <?php if ($userRole === 'admin'): ?>
            <div class="stat-box">
                <div class="stat-icon-circle" style="color: var(--secondary); background: rgba(139, 92, 246, 0.05);">🌍</div>
                <div><span class="stat-val counter" data-count="<?= (int)($globalStats['total_votos'] ?? 0) ?>">0</span><span class="stat-lbl">Total Vox Angola</span></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="main-grid">
        <div class="main-col">
            <div class="card" style="margin-bottom: 2rem;">
                <div class="card-header">
                    <h2><?= $userRole === 'admin' ? 'Auditoria de Atividade Recente' : 'Minha Atividade Recente' ?></h2>
                    <span style="font-size: 0.75rem; color: var(--gray-500);"><?= $userRole === 'admin' ? 'Últimos 10 registos globais' : 'Seus últimos 10 registos' ?></span>
                </div>
                <div class="table-container">
                    <table class="vox-table">
                        <thead>
                            <tr>
                                <th>Actor</th>
                                <th>Acção</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $act): ?>
                                <tr>
                                    <td style="font-weight: 700;"><?= htmlspecialchars($act['user_nome'] ?? 'Sistema') ?></td>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($act['acao']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?= htmlspecialchars($act['detalhes']) ?></div>
                                    </td>
                                    <td style="color: var(--gray-500); font-size: 0.85rem;"><?= formatDate($act['criado_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentActivity)): ?>
                                <tr><td colspan="3" style="text-align: center; color: var(--gray-500); padding: 3rem;">Nenhuma atividade registada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($pendingReports)): ?>
            <div class="card" style="margin-bottom: 2rem; border-left: 4px solid var(--danger);">
                <div class="card-header">
                    <h2 style="color: var(--danger);"><i class="fa fa-exclamation-triangle"></i> Denúncias Pendentes</h2>
                    <span style="font-size: 0.75rem; color: var(--gray-500);">Ações corretivas necessárias</span>
                </div>
                <div class="table-container">
                    <table class="vox-table">
                        <thead>
                            <tr>
                                <th>Alvo</th>
                                <th>Motivo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingReports as $rep): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($rep['target_name']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--gray-500);">Denunciado por: <?= htmlspecialchars($rep['autor_nome']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:rgba(239, 68, 68, 0.1); color:var(--danger);"><?= htmlspecialchars($rep['motivo']) ?></span>
                                        <div style="font-size:0.8rem; margin-top:0.25rem;"><?= htmlspecialchars(substr($rep['detalhes'], 0, 50)) ?>...</div>
                                    </td>
                                    <td style="font-size:0.85rem; color:var(--gray-500);"><?= formatDate($rep['criado_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($userRole === 'organizador' && !empty($userSalas)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2>Desempenho das Minhas Salas</h2>
                    </div>
                    <div class="table-container">
                        <table class="vox-table">
                            <thead>
                                <tr>
                                    <th>Sala</th>
                                    <th>Estado</th>
                                    <th>Votos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userSalas as $sala): ?>
                                    <tr>
                                        <td style="font-weight: 700;"><?= htmlspecialchars($sala['nome']) ?></td>
                                        <td><span class="badge badge-<?= strtolower($sala['estado']) ?>"><?= ucfirst($sala['estado']) ?></span></td>
                                        <td style="font-weight: 900; color: var(--primary);"><?= (int)$sala['total_votos'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($userRole === 'admin'): ?>
            <div class="side-col">
                <div class="eco-sidebar-card" style="margin-bottom: 2rem;">
                    <h3 style="font-weight: 900; margin-bottom: 1.5rem; letter-spacing: -0.5px;">Ecossistema Vox</h3>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div>
                            <div style="font-size: 0.85rem; opacity: 0.6; margin-bottom: 0.25rem;">Eleições Ativas</div>
                            <div style="font-size: 1.5rem; font-weight: 800;"><?= (int)($globalStats['total_eleicoes'] ?? 0) ?></div>
                        </div>
                        <div style="padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                            <div style="font-size: 0.85rem; opacity: 0.6; margin-bottom: 0.25rem;">Cidadãos Inscritos</div>
                            <div style="font-size: 1.5rem; font-weight: 800;"><?= (int)($globalStats['total_usuarios'] ?? 1) ?></div>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding: 2rem; background: #eff6ff; border-color: #bfdbfe;">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">🛡️</div>
                    <h3 style="font-weight: 800; margin-bottom: 0.75rem; color: #1e3a8a;">Segurança S.T.E.</h3>
                    <p style="font-size: 0.9rem; color: #3b82f6; line-height: 1.5; font-weight: 500;">
                        Os dados exibidos neste painel são auditados constantemente para garantir que nenhum voto seja alterado ou removido.
                    </p>
                    <a href="seguranca.php" style="display: inline-block; margin-top: 1.5rem; font-weight: 800; color: #1e3a8a; text-decoration: none; font-size: 0.85rem;">Ver Relatório de Segurança →</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                if (isNaN(target)) return;
                
                let count = 0;
                const duration = 1500;
                const increment = target / (duration / 16);
                
                const timer = setInterval(() => {
                    count += increment;
                    if (count >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(count).toLocaleString();
                    }
                }, 16);
            });
        });
    </script>
<?php require 'includes/footer.php'; ?>
