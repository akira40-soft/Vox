<?php
/**
 * Navbar Component - Include this in all authenticated pages
 * Requires: PHP session with user_id, user_nome, user_role
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userFirstLetter = strtoupper(substr($_SESSION['user_nome'] ?? 'User', 0, 1));
$userRole = $_SESSION['user_role'] ?? 'eleitor';
?>

<style>
:root {
    --primary: #3b82f6;
    --primary-dark: #1e3a8a;
    --secondary: #8b5cf6;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --border: #e5e7eb;
}

.vox-navbar {
    background: white;
    padding: 1rem 2rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.navbar-brand {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none;
    cursor: pointer;
}

.navbar-center {
    flex: 1;
    max-width: 400px;
    margin: 0 2rem;
}

.navbar-search {
    width: 100%;
    padding: 0.6rem 1rem;
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 0.9rem;
    font-family: inherit;
}

.navbar-search:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.navbar-actions {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.navbar-icon-btn {
    background: none;
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    color: var(--text-secondary);
    transition: color 0.3s;
    position: relative;
}

.navbar-icon-btn:hover {
    color: var(--primary);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
}

.navbar-user {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    cursor: pointer;
    position: relative;
}

.user-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.user-info strong {
    font-size: 0.9rem;
    color: var(--text-primary);
}

.user-info small {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    min-width: 200px;
    margin-top: 0.5rem;
    display: none;
    z-index: 1000;
}

.dropdown-menu.active {
    display: block;
}

.dropdown-menu a, .dropdown-menu button {
    display: block;
    width: 100%;
    padding: 0.8rem 1rem;
    border: none;
    background: none;
    text-align: left;
    color: var(--text-secondary);
    text-decoration: none;
    cursor: pointer;
    border-bottom: 1px solid var(--border);
    transition: background 0.3s, color 0.3s;
    font-family: inherit;
    font-size: 0.9rem;
}

.dropdown-menu a:last-child, .dropdown-menu button:last-child {
    border-bottom: none;
}

.dropdown-menu a:hover, .dropdown-menu button:hover {
    background: var(--primary);
    color: white;
}

.dropdown-menu .danger {
    color: #ef4444;
}

.dropdown-menu .danger:hover {
    background: #ef4444;
    color: white;
}

/* Mobile */
@media (max-width: 768px) {
    .vox-navbar {
        flex-wrap: wrap;
        gap: 1rem;
    }
    .navbar-center {
        order: 3;
        flex-basis: 100%;
        max-width: 100%;
        margin: 0;
    }
    .user-info {
        display: none;
    }
}
</style>

<nav class="vox-navbar">
    <a href="home.php" class="navbar-brand">🗳️ Vox</a>
    
    <div class="navbar-center">
        <input type="search" class="navbar-search" placeholder="🔍 Procurar votações, campanhas...">
    </div>
    
    <div class="navbar-actions">
        <button class="navbar-icon-btn" id="notifBtn" title="Notificações">
            🔔
            <span class="notification-badge">3</span>
        </button>
        
        <div class="navbar-user" id="userMenuBtn">
            <div class="user-avatar-sm"><?= htmlspecialchars($userFirstLetter) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars(substr($_SESSION['user_nome'] ?? 'User', 0, 15)) ?></strong>
                <small><?= ucfirst($userRole) ?></small>
            </div>
            
            <div class="dropdown-menu" id="userMenu">
                <a href="perfil.php">⚙️ Configurações</a>
                <a href="#notifications">🔔 Notificações</a>
                <?php if ($userRole === 'organizador'): ?>
                    <a href="minhas_salas.php">📁 Minhas Salas</a>
                    <a href="criar_sala.php">➕ Criar Votação</a>
                <?php elseif ($userRole === 'candidato'): ?>
                    <a href="minhas_candidaturas.php">🎯 Minhas Candidaturas</a>
                    <a href="criar_campanha.php">📝 Nova Campanha</a>
                <?php endif; ?>
                <button class="danger" onclick="if(confirm('Tem certeza que deseja sair?')) location.href='logout.php'">🚪 Sair</button>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userMenu = document.getElementById('userMenu');
    
    userMenuBtn.addEventListener('click', function() {
        userMenu.classList.toggle('active');
    });
    
    document.addEventListener('click', function(e) {
        if (!userMenuBtn.contains(e.target)) {
            userMenu.classList.remove('active');
        }
    });
});
</script>
