<?php
/**
 * navbar-landing.php - Navbar para Landing Page
 * Funciona tanto para usuários autenticados como não-autenticados
 * Adapta os botões CTA dependendo do status de autenticação
 */

// Verificar se está autenticado (sem redirecionar)
$isAuthenticated = isset($_SESSION['user_id']);
$userName = $_SESSION['user_nome'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;
?>

<style>
:root {
    --primary: #3b82f6;
    --primary-dark: #1e3a8a;
    --secondary: #8b5cf6;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --border: #e5e7eb;
    --bg-white: #ffffff;
}

.navbar-landing {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: var(--bg-white);
    border-bottom: 1px solid var(--border);
    padding: 1rem 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.navbar-landing-container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.navbar-landing-brand {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
}

.navbar-landing-links {
    display: flex;
    gap: 2.5rem;
    list-style: none;
    flex: 1;
}

.navbar-landing-links a {
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 500;
    transition: color 0.3s;
    font-size: 0.95rem;
}

.navbar-landing-links a:hover {
    color: var(--primary);
}

.navbar-landing-cta {
    display: flex;
    gap: 0.8rem;
    align-items: center;
    white-space: nowrap;
}

.btn-landing {
    padding: 0.6rem 1.4rem;
    border-radius: 6px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-block;
}

.btn-landing-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.btn-landing-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
}

.btn-landing-ghost {
    background: none;
    color: var(--text-secondary);
    border: 1px solid var(--border);
}

.btn-landing-ghost:hover {
    color: var(--primary);
    border-color: var(--primary);
}

.user-menu-landing{
    position: relative;
}

.user-trigger {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-primary);
    font-weight: 600;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    transition: background 0.3s;
}

.user-trigger:hover {
    background: var(--border);
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    min-width: 200px;
    margin-top: 0.5rem;
    display: none;
    z-index: 1001;
}

.user-dropdown.active {
    display: block;
}

.user-dropdown a,
.user-dropdown button {
    display: block;
    width: 100%;
    padding: 0.85rem 1rem;
    border: none;
    background: none;
    text-align: left;
    color: var(--text-secondary);
    text-decoration: none;
    cursor: pointer;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s, color 0.2s;
    font-family: inherit;
    font-size: 0.9rem;
}

.user-dropdown a:last-child,
.user-dropdown button:last-child {
    border-bottom: none;
}

.user-dropdown a:hover,
.user-dropdown button:hover {
    background: var(--primary);
    color: white;
}

.user-dropdown .danger {
    color: #ef4444;
}

.user-dropdown .danger:hover {
    background: #ef4444;
}

/* Mobile */
@media (max-width: 768px) {
    .navbar-landing {
        padding: 1rem;
    }

    .navbar-landing-container {
        gap: 1rem;
        flex-wrap: nowrap;
    }

    .navbar-landing-brand {
        font-size: 1.5rem;
    }

    .navbar-landing-links {
        display: none;
    }

    .navbar-landing-cta {
        gap: 0.5rem;
    }

    .btn-landing {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
}
</style>

<nav class="navbar-landing">
    <div class="navbar-landing-container">
        <a href="index.php" class="navbar-landing-brand">🗳️ Vox</a>
        
        <ul class="navbar-landing-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="sobre-nos.php">Sobre-nós</a></li>
            <li><a href="contactos.php">Contactos</a></li>
            <li><a href="seguranca.php">Segurança</a></li>
        </ul>
        
        <div class="navbar-landing-cta">
            <?php if ($isAuthenticated && $userName): ?>
                <!-- Usuário Autenticado -->
                <div class="user-menu-landing">
                    <button class="user-trigger" id="userTriggerLanding">
                        👤 <?= htmlspecialchars(substr($userName, 0, 12)) ?>
                        <span style="font-size: 0.8rem; margin-left: 0.3rem;">▼</span>
                    </button>
                    <div class="user-dropdown" id="userDropdownLanding">
                        <a href="dashboard.php">📊 Meu Painel</a>
                        <a href="perfil.php">⚙️ Perfil</a>
                        <button class="danger" onclick="if(confirm('Sair da plataforma?')) location.href='logout.php'">🚪 Sair</button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Não Autenticado -->
                <a href="login.php" class="btn-landing btn-landing-ghost">Entrar</a>
                <a href="registo.php" class="btn-landing btn-landing-primary">Começar Grátis</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userTrigger = document.getElementById('userTriggerLanding');
    const userDropdown = document.getElementById('userDropdownLanding');

    if (userTrigger) {
        userTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function() {
            userDropdown.classList.remove('active');
        });

        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});
</script>
