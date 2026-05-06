<?php
/**
 * admin.php - Vox Electoral Platform
 * Admin panel: system stats, user management, election management, audit log
 */
require_once 'config/helpers.php';
$adminUser = requireAdmin();
$userId = $adminUser['id'] ?? $_SESSION['user_id'];

generateCSRFToken();

// ---- POST Handlers (Handle before any HTML output for clean redirects) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation for all admin actions
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('error', 'Sessão expirada ou token inválido. Tente novamente.');
        redirect('admin.php');
    }

    // Approve user
    if (isset($_POST['aprovar_usuario'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE users SET estado = 'ativo' WHERE id = ? AND estado = 'pendente'")->execute([$uid]);
        
        // Notify the user they were approved
        notifyUser($uid, 'success', 'Parabéns! A sua conta foi aprovada pelos organizadores.', 'perfil.php');
        
        auditLog($userId, 'aprovar_usuario', "Utilizador aprovado: id=$uid");
        setFlash('success', 'Utilizador aprovado com sucesso.');
        redirect('admin.php?acao=usuarios');
    }

    // Ban user
    if (isset($_POST['banir_usuario'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE users SET estado = 'banido' WHERE id = ? AND estado = 'ativo'")->execute([$uid]);
        auditLog($userId, 'banir_usuario', "Utilizador banido: id=$uid");
        setFlash('success', 'Utilizador banido com sucesso.');
        redirect('admin.php?acao=usuarios');
    }

    // Unban user
    if (isset($_POST['desbanir_usuario'])) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $pdo->prepare("UPDATE users SET estado = 'ativo' WHERE id = ? AND estado = 'banido'")->execute([$uid]);
        auditLog($userId, 'desbanir_usuario', "Utilizador reativado: id=$uid");
        setFlash('success', 'Utilizador reativado com sucesso.');
        redirect('admin.php?acao=usuarios');
    }

    // Change sala status
    if (isset($_POST['alterar_estado_sala'])) {
        $sid = (int)($_POST['sala_id'] ?? 0);
        $novoEstado = sanitize($_POST['novo_estado'] ?? 'pausada');
        $validos = ['ativa', 'pausada', 'finalizada', 'cancelada'];
        if (in_array($novoEstado, $validos) && $sid > 0) {
            $pdo->prepare("UPDATE salas_eleitorais SET estado = ? WHERE id = ?")->execute([$novoEstado, $sid]);
            auditLog($userId, 'alterar_estado_sala', "Sala $sid mudou para $novoEstado");
            setFlash('success', 'Estado da sala atualizado.');
            redirect('admin.php?acao=eleicoes');
        }
    }

    // Delete sala
    if (isset($_POST['apagar_sala'])) {
        $sid = (int)($_POST['sala_id'] ?? 0);
        $pdo->prepare("DELETE FROM salas_eleitorais WHERE id = ?")->execute([$sid]);
        auditLog($userId, 'apagar_sala', "Sala apagada: id=$sid");
        setFlash('success', 'Sala apagada com sucesso.');
        redirect('admin.php?acao=eleicoes');
    }
}

$acao = sanitize($_GET['acao'] ?? 'dashboard');
$pageTitle = 'Administracao';
require 'includes/header.php';
?>

<div class="dashboard-content">

    <div class="dashboard-panel" style="padding: 2.5rem; margin-bottom: 2rem; background: var(--blue-deeper); color: white; border: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: white;"> Administração do Sistema</h2>
                <p style="opacity: 0.8; font-size: 1.1rem;">Controlo central de utilizadores, eleições e auditoria de segurança.</p>
            </div>
            <div class="top-bar-actions">
                <a href="dashboard.php" class="btn btn-primary" style="background: white; color: var(--blue-deeper); border: none; font-weight: 800; padding: 0.75rem 1.5rem;">Voltar ao Painel</a>
            </div>
        </div>
    </div>

    <!-- Sub Navigation -->
    <div style="display:flex; gap:1rem; margin-bottom:2rem; flex-wrap:wrap;">
        <a href="admin.php?acao=dashboard" class="btn <?= $acao === 'dashboard' ? 'btn-primary' : 'btn-ghost' ?>" style="padding: 0.75rem 1.5rem; border-radius: 999px;">📊 Painel</a>
        <a href="admin.php?acao=usuarios" class="btn <?= $acao === 'usuarios' ? 'btn-primary' : 'btn-ghost' ?>" style="padding: 0.75rem 1.5rem; border-radius: 999px;">👥 Utilizadores</a>
        <a href="admin.php?acao=eleicoes" class="btn <?= $acao === 'eleicoes' ? 'btn-primary' : 'btn-ghost' ?>" style="padding: 0.75rem 1.5rem; border-radius: 999px;">🏠 Eleições</a>
        <a href="admin.php?acao=auditoria" class="btn <?= $acao === 'auditoria' ? 'btn-primary' : 'btn-ghost' ?>" style="padding: 0.75rem 1.5rem; border-radius: 999px;">📄 Auditoria</a>
    </div>

    <?php if ($acao === 'dashboard' || $acao === ''): ?>
    <?php
    $stmt = $pdo->query("SELECT metrica, valor FROM estatisticas_sistema");
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE estado = 'pendente'");
    $pendingUsers = (int)$stmt->fetchColumn();
    ?>
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <a href="admin.php?acao=usuarios" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon blue">👥</div>
            <div class="stat-info">
                <h3><?= number_format((int)($stats['total_usuarios'] ?? 0)) ?></h3>
                <p>Total Utilizadores</p>
            </div>
        </a>
        <a href="admin.php?acao=eleicoes" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon green">🗳️</div>
            <div class="stat-info">
                <h3><?= number_format((int)($stats['total_eleicoes'] ?? 0)) ?></h3>
                <p>Eleições Realizadas</p>
            </div>
        </a>
        <a href="admin.php?acao=auditoria" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon purple">📑</div>
            <div class="stat-info">
                <h3><?= number_format((int)($stats['total_votos'] ?? 0)) ?></h3>
                <p>Votos Submetidos</p>
            </div>
        </a>
        <a href="admin.php?acao=usuarios&filtro=pendente" class="stat-card" style="text-decoration: none; color: inherit;">
            <div class="stat-icon orange">⏳</div>
            <div class="stat-info">
                <h3><?= number_format($pendingUsers) ?></h3>
                <p>Aguardam Aprovação</p>
            </div>
        </a>
    </div>

    <div class="dashboard-panel full-width">
        <div class="panel-header" style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--gray-100); background: var(--gray-50);">
            <h2 style="font-size: 1.15rem; font-weight: 800;">Atividade Recente</h2>
            <a href="admin.php?acao=auditoria" class="btn btn-ghost btn-sm" style="font-weight: 700;">Log Completo →</a>
        </div>
        <div class="activity-table">
        <?php
        $stmt = $pdo->query("
            SELECT a.*, u.nome_completo as user_nome
            FROM auditoria a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.criado_em DESC
            LIMIT 12
        ");
        $activities = $stmt->fetchAll();
        if (!empty($activities)):
        ?>
        <table>
            <thead>
                <tr>
                    <th>Utilizador</th>
                    <th>Acao</th>
                    <th>Detalhes</th>
                    <th>IP</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($activities as $act): ?>
            <tr>
                <td><?= htmlspecialchars($act['user_nome'] ?? 'Sistema') ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($act['acao']) ?></span></td>
                <td><?= htmlspecialchars($act['detalhes'] ?? '') ?></td>
                <td><code style="font-size:0.8rem;"><?= htmlspecialchars($act['ip'] ?? '') ?></code></td>
                <td><?= formatDate($act['criado_em']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">&#128196;</span>
            <p>Sem atividade registada.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($acao === 'usuarios'): ?>
    <?php
    $filtro = sanitize($_GET['filtro'] ?? 'todos');
    $cond = '';
    switch ($filtro) {
        case 'pendente': $cond = "WHERE estado = 'pendente'"; break;
        case 'ativo': $cond = "WHERE estado = 'ativo'"; break;
        case 'banido': $cond = "WHERE estado = 'banido'"; break;
        default: $cond = ''; break;
    }
    $stmt = $pdo->query("SELECT * FROM users $cond ORDER BY criado_em DESC");
    $users = $stmt->fetchAll();
    ?>
    <div class="dashboard-panel full-width">
        <div class="panel-header">
            <h2>Gerir Utilizadores</h2>
            <div style="display:flex;gap:0.5rem;">
                <a href="admin.php?acao=usuarios&filtro=todos" class="btn btn-<?= $filtro === 'todos' ? 'primary' : 'ghost' ?> btn-sm">Todos</a>
                <a href="admin.php?acao=usuarios&filtro=pendente" class="btn btn-<?= $filtro === 'pendente' ? 'warning' : 'ghost' ?> btn-sm">Pendentes</a>
                <a href="admin.php?acao=usuarios&filtro=banido" class="btn btn-<?= $filtro === 'banido' ? 'danger' : 'ghost' ?> btn-sm">Banidos</a>
            </div>
        </div>
        <div class="activity-table">
        <?php if (!empty($users)): ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Estado</th>
                    <th>Registo</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:0.75rem;background:linear-gradient(135deg,var(--blue),var(--purple));"><?= strtoupper(substr($u['nome_completo'],0,1)) ?></div>
                        <?= htmlspecialchars($u['nome_completo']) ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span></td>
                <td><span class="badge badge-<?= htmlspecialchars($u['estado']) ?>"><?= htmlspecialchars(ucfirst($u['estado'])) ?></span></td>
                <td><?= formatDate($u['criado_em']) ?></td>
                <td>
                    <div class="admin-actions">
                    <?php if ($u['estado'] === 'pendente'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="aprovar_usuario" class="btn btn-success btn-sm">Aprovar</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($u['estado'] === 'ativo' && $u['role'] !== 'admin'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tem a certeza que deseja banir este utilizador?');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="banir_usuario" class="btn btn-danger btn-sm">Banir</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($u['estado'] === 'banido'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="desbanir_usuario" class="btn btn-ghost btn-sm">Reativar</button>
                    </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">&#128100;</span>
            <p>Nenhum utilizador encontrado.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($acao === 'eleicoes'): ?>
    <?php
    $stmt = $pdo->query("
        SELECT s.*, u.nome_completo as organizador, p.nome as provincia_nome,
               (SELECT COUNT(*) FROM votos WHERE sala_id = s.id) as total_votos
        FROM salas_eleitorais s
        JOIN users u ON s.organizador_id = u.id
        LEFT JOIN provincias p ON s.provincia_origem = p.id
        ORDER BY s.criado_em DESC
    ");
    $salas = $stmt->fetchAll();
    ?>
    <div class="dashboard-panel full-width">
        <div class="panel-header">
            <h2>Gerir Eleicoes</h2>
        </div>
        <div class="activity-table">
        <?php if (!empty($salas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Organizador</th>
                    <th>Estado</th>
                    <th>Votos</th>
                    <th>Criacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salas as $s): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($s['nome']) ?></strong>
                    <br><code style="font-size:0.75rem;color:var(--gray-500);"><?= htmlspecialchars($s['codigo_acesso']) ?></code>
                </td>
                <td><span class="badge badge-info"><?= htmlspecialchars($s['tipo']) ?></span></td>
                <td><?= htmlspecialchars($s['organizador']) ?></td>
                <td><span class="badge badge-<?= htmlspecialchars(strtolower($s['estado'])) ?>"><?= htmlspecialchars(ucfirst($s['estado'])) ?></span></td>
                <td><?= number_format((int)$s['total_votos']) ?></td>
                <td><?= formatDate($s['criado_em']) ?></td>
                <td>
                    <div class="admin-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="sala_id" value="<?= $s['id'] ?>">
                        <?php if ($s['estado'] === 'ativa'): ?>
                        <input type="hidden" name="novo_estado" value="pausada">
                        <button type="submit" name="alterar_estado_sala" class="btn btn-warning btn-sm">Pausar</button>
                        <?php elseif ($s['estado'] === 'pausada'): ?>
                        <input type="hidden" name="novo_estado" value="ativa">
                        <button type="submit" name="alterar_estado_sala" class="btn btn-success btn-sm">Ativar</button>
                        <?php elseif ($s['estado'] === 'rascunho'): ?>
                        <input type="hidden" name="novo_estado" value="ativa">
                        <button type="submit" name="alterar_estado_sala" class="btn btn-success btn-sm">Ativar</button>
                        <?php endif; ?>
                        <?php if (in_array($s['estado'], ['ativa', 'pausada'])): ?>
                        <input type="hidden" name="novo_estado" value="finalizada">
                        <button type="submit" name="alterar_estado_sala" class="btn btn-ghost btn-sm">Finalizar</button>
                        <?php endif; ?>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tem a certeza que deseja apagar esta sala?');">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="sala_id" value="<?= $s['id'] ?>">
                        <button type="submit" name="apagar_sala" class="btn btn-danger btn-sm">Apagar</button>
                    </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">&#127968;</span>
            <p>Nenhuma sala eleitoral criada.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($acao === 'auditoria'): ?>
    <?php
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 30;
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->query("SELECT COUNT(*) FROM auditoria");
    $totalRows = (int)$stmt->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);

    $stmt = $pdo->prepare("
        SELECT a.*, u.nome_completo as user_nome
        FROM auditoria a
        LEFT JOIN users u ON a.user_id = u.id
        ORDER BY a.criado_em DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$perPage, $offset]);
    $auditLogs = $stmt->fetchAll();
    ?>
    <div class="dashboard-panel full-width">
        <div class="panel-header">
            <h2>Log de Auditoria</h2>
            <span style="color:var(--gray-500);font-size:0.85rem;"><?= $totalRows ?> registos</span>
        </div>
        <div class="activity-table">
        <?php if (!empty($auditLogs)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Utilizador</th>
                    <th>Acao</th>
                    <th>Detalhes</th>
                    <th>IP</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($auditLogs as $log): ?>
            <tr>
                <td><?= (int)$log['id'] ?></td>
                <td><?= htmlspecialchars($log['user_nome'] ?? 'Sistema') ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($log['acao']) ?></span></td>
                <td><?= htmlspecialchars($log['detalhes'] ?? '') ?></td>
                <td><code style="font-size:0.8rem;"><?= htmlspecialchars($log['ip'] ?? '') ?></code></td>
                <td><?= formatDate($log['criado_em']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:0.5rem;padding:1rem;">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="admin.php?acao=auditoria&page=<?= $p ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <span class="empty-icon">&#128196;</span>
            <p>Nenhum registo de auditoria.</p>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require 'includes/footer.php'; ?>
