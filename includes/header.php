<?php
/**
 * header.php - Vox Master Dynamic Header
 * Integrated navigation with dropdown submenus (VaultEdge pattern).
 */
require_once 'config/helpers.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName   = $_SESSION['user_nome'] ?? 'Utilizador';
$userRole   = strtolower(trim($_SESSION['user_role'] ?? 'eleitor'));
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch unread notifications count
$notifCount = 0;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE user_id = ? AND lida = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notifCount = (int)$stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Vox') ?> — Vox Electoral</title>

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <!-- Master Stylesheet (single load) -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">

    <!-- Data Visualization: Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Theme engine: runs BEFORE page renders to prevent flash -->
    <script>
        (function() {
            var t = localStorage.getItem('vox-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>

    <header class="master-header" id="masterHeader">
        <a href="index.php" class="logo">
            <img src="assets/images/vox.jpg" alt="Vox Logo" style="height: 38px; width: auto; border-radius: 8px; margin-right: 12px; display: inline-block; vertical-align: middle; background: white; padding: 2px;">
            <span class="logo-text">Vox</span>
        </a>

        <nav class="main-nav" id="mainNav">
            <ul>
                <?php if ($isLoggedIn): ?>
                    <li><a href="home.php" class="<?= $currentPage === 'home.php' ? 'active' : '' ?>">Início</a></li>

                    <?php if (in_array($userRole, ['organizador', 'admin', 'candidatos', 'candidato'])): ?>
                        <li class="has-drop">
                            <a href="minhas_salas.php" class="<?= in_array($currentPage, ['minhas_salas.php','criar_sala.php']) ? 'active' : '' ?>">
                                Eleições <i class="fa fa-angle-down"></i>
                            </a>
                            <ul class="nav-dropdown">
                                <li><a href="minhas_salas.php"><i class="fa fa-list"></i> &nbsp;Minhas Salas</a></li>
                                <li><a href="criar_sala.php"><i class="fa fa-plus-circle"></i> &nbsp;Criar Eleição</a></li>
                                <li><a href="dashboard.php"><i class="fa fa-bar-chart"></i> &nbsp;Dashboard</a></li>
                            </ul>
                        </li>
                        <li><a href="candidatos.php" class="<?= $currentPage === 'candidatos.php' ? 'active' : '' ?>">Candidaturas</a></li>
                    <?php else: ?>
                        <li><a href="votar_publico.php" class="<?= $currentPage === 'votar_publico.php' ? 'active' : '' ?>">Votação Aberta</a></li>
                        <li><a href="candidatos.php" class="<?= $currentPage === 'candidatos.php' ? 'active' : '' ?>">Candidaturas</a></li>
                        <li><a href="minhas_votacoes.php" class="<?= $currentPage === 'minhas_votacoes.php' ? 'active' : '' ?>">Minha Atividade</a></li>
                    <?php endif; ?>

                    <li><a href="sobre-nos.php" class="<?= $currentPage === 'sobre-nos.php' ? 'active' : '' ?>">Sobre Nós</a></li>
                    <li><a href="contactos.php" class="<?= $currentPage === 'contactos.php' ? 'active' : '' ?>">Contactos</a></li>

                <?php else: ?>
                    <li class="has-drop">
                        <a href="servicos.php" class="<?= in_array($currentPage, ['servicos.php','precos.php']) ? 'active' : '' ?>">
                            Produto <i class="fa fa-angle-down"></i>
                        </a>
                        <ul class="nav-dropdown">
                            <li><a href="servicos.php"><i class="fa fa-cogs"></i> &nbsp;Serviços</a></li>
                            <li><a href="precos.php"><i class="fa fa-tag"></i> &nbsp;Preços</a></li>
                            <li><a href="seguranca.php"><i class="fa fa-shield"></i> &nbsp;Segurança</a></li>
                        </ul>
                    </li>
                    <li class="has-drop">
                        <a href="sobre-nos.php" class="<?= in_array($currentPage, ['sobre-nos.php','contactos.php']) ? 'active' : '' ?>">
                            Empresa <i class="fa fa-angle-down"></i>
                        </a>
                        <ul class="nav-dropdown">
                            <li><a href="sobre-nos.php"><i class="fa fa-users"></i> &nbsp;Sobre Nós</a></li>
                            <li><a href="contactos.php"><i class="fa fa-envelope"></i> &nbsp;Contactos</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="auth-actions">
            <!-- Theme Toggle -->
            <button class="nav-icon-btn" id="themeToggle" title="Alternar Tema" aria-label="Alternar tema">
                <i class="fa fa-moon-o"></i>
            </button>

            <!-- Mobile Hamburger -->
            <button class="nav-hamburger" id="navHamburger" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <?php if ($isLoggedIn): ?>
                <!-- Notifications -->
                <div style="position: relative;">
                    <button class="nav-icon-btn" id="notifToggle" aria-label="Notificações">
                        <i class="fa fa-bell-o"></i>
                        <span class="notification-badge" id="notifBadge" style="<?= $notifCount > 0 ? '' : 'display:none;' ?>background: #ef4444; color: white; position: absolute; top: -5px; right: -5px; font-size: 0.65rem; padding: 2px 5px; border-radius: 10px; font-weight: 900; border: 2px solid var(--bg-body);">
                            <?= $notifCount > 9 ? '9+' : $notifCount ?>
                        </span>
                    </button>
                    <div class="dropdown-menu" id="notifDropdown" style="width: 320px; right: 0; left: auto;">
                        <div style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); margin-bottom: 0.5rem;">
                            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-header); margin: 0;">Notificações</h4>
                            <button id="markAllRead" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; font-weight: 600; cursor: pointer; padding: 0;">Limpar</button>
                        </div>
                        <div id="notiList">
                            <!-- JS populated -->
                            <div class="dropdown-item" style="justify-content: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem;">
                                <i class="fa fa-spinner fa-spin"></i> &nbsp; A carregar...
                            </div>
                        </div>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.5rem 0;">
                        <a href="notificacoes.php" style="display: block; text-align: center; font-size: 0.8rem; color: var(--primary); font-weight: 700; padding: 0.5rem;">Ver Todas as Notificações</a>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="user-dropdown" id="userMenuToggle" style="position:relative; cursor:pointer;">
                    <div class="avatar-circle" title="<?= htmlspecialchars($userName) ?>">
                        <?= strtoupper(substr($userName, 0, 1)) ?>
                    </div>
                    <div class="dropdown-menu" id="userDropdown" style="right: 0; left: auto; min-width: 220px;">
                        <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); margin-bottom: 0.5rem;">
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-header);"><?= htmlspecialchars($userName) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize;"><?= htmlspecialchars($userRole) ?></div>
                        </div>
                        <a href="perfil.php" class="dropdown-item"><i class="fa fa-user-circle-o"></i> &nbsp;Meu Perfil</a>
                        <a href="notificacoes.php" class="dropdown-item"><i class="fa fa-bell-o"></i> &nbsp;Notificações</a>
                        <?php if ($userRole === 'admin' || $userRole === 'organizador'): ?>
                            <a href="dashboard.php" class="dropdown-item"><i class="fa fa-bar-chart"></i> &nbsp;Dashboard</a>
                        <?php endif; ?>
                        <?php if ($userRole === 'admin'): ?>
                            <a href="admin.php" class="dropdown-item"><i class="fa fa-shield"></i> &nbsp;Administração</a>
                        <?php endif; ?>
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.5rem 0;">
                        <a href="logout.php" class="dropdown-item logout"><i class="fa fa-sign-out"></i> &nbsp;Terminar Sessão</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-nav">Entrar</a>
                <a href="registo.php" class="btn-nav btn-nav-primary">Começar Grátis</a>
            <?php endif; ?>
        </div>
    </header>

    <script src="assets/js/theme.js?v=<?= time() ?>" defer></script>

    <main id="main-content">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div style="padding: 1rem 5%; background: var(--primary); color: white; font-weight: 600; text-align: center; font-size: 0.9rem; animation: slideInAlert 0.4s ease-out;">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <style>@keyframes slideInAlert { from { opacity: 0; transform: translateY(-100%); } to { opacity: 1; transform: translateY(0); } }</style>
        <?php endif; ?>

<script>
/**
 * Globally available function to update the notification badge UI
 * @param {number} count - New unread count
 */
function updateBadgeUI(count) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;
    
    if (count > 0) {
        badge.style.display = 'block';
        badge.textContent = count > 9 ? '9+' : count;
    } else {
        badge.style.display = 'none';
        badge.textContent = '0';
    }
}
</script>
