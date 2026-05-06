<?php
/**
 * notificacoes.php - Vox Electoral Platform
 * User Interface for browsing all notifications
 */
require_once 'config/helpers.php';
$userId = requireAuth();

$pdo = getDB();

// Handle Mark All as Read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = TRUE WHERE user_id = ? AND lida = FALSE");
    $stmt->execute([$userId]);
    $_SESSION['flash_message'] = "Todas as notificações foram marcadas como lidas.";
    header("Location: notificacoes.php");
    exit;
}

// Fetch all notifications for this user
$stmt = $pdo->prepare("
    SELECT id, mensagem, tipo, lida, link, criado_em 
    FROM notificacoes 
    WHERE user_id = ? 
    ORDER BY lida ASC, criado_em DESC
");
$stmt->execute([$userId]);
$notificacoes = $stmt->fetchAll();

$pageTitle = 'Minhas Notificações';
require_once 'includes/header.php';
?>

<style>
    .notif-container {
        max-width: 800px;
        margin: 0 auto;
        padding-bottom: 5rem;
    }

    .notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .notif-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        margin-bottom: 1rem;
        display: flex;
        gap: 1.5rem;
        transition: var(--transition);
        position: relative;
    }

    .notif-card.unread {
        border-left: 4px solid var(--primary);
        background: rgba(59, 130, 246, 0.03);
    }

    .notif-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .notif-icon-info { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
    .notif-icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
    .notif-icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--orange); }
    .notif-icon-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

    .notif-body { flex: 1; }
    .notif-msg { color: var(--text-main); font-weight: 500; margin-bottom: 0.25rem; }
    .notif-time { font-size: 0.8rem; color: var(--text-muted); }

    .notif-badge {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--primary);
    }

    .empty-notif {
        text-align: center;
        padding: 5rem 2rem;
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px dashed var(--border-color);
        color: var(--text-muted);
    }
</style>

<div class="notif-container">
    <div class="notif-header">
        <h1 class="ve-title" style="margin-bottom: 0;">Notificações</h1>
        <?php if (count($notificacoes) > 0): ?>
            <button type="button" id="btnMarkAll" class="btn btn-ghost btn-sm" onclick="markAllNotificationsRead()">
                <i class="fa fa-check-all"></i> Marcar todas como lidas
            </button>
        <?php endif; ?>
    </div>

    <?php if (count($notificacoes) > 0): ?>
        <div class="notif-list">
            <?php foreach ($notificacoes as $n): 
                $iconClass = 'notif-icon-info';
                $icon = 'fa-info-circle';
                
                if (strpos(strtolower($n['tipo']), 'erro') !== false || strpos(strtolower($n['tipo']), 'danger') !== false) {
                    $iconClass = 'notif-icon-danger'; $icon = 'fa-exclamation-triangle';
                } elseif (strpos(strtolower($n['tipo']), 'sucesso') !== false || strpos(strtolower($n['tipo']), 'success') !== false) {
                    $iconClass = 'notif-icon-success'; $icon = 'fa-check-circle';
                } elseif (strpos(strtolower($n['tipo']), 'aviso') !== false || strpos(strtolower($n['tipo']), 'warning') !== false) {
                    $iconClass = 'notif-icon-warning'; $icon = 'fa-warning';
                }
            ?>
                <div class="notif-card <?= !$n['lida'] ? 'unread' : '' ?>">
                    <div class="notif-icon-circle <?= $iconClass ?>">
                        <i class="fa <?= $icon ?>"></i>
                    </div>
                    <div class="notif-body">
                        <div class="notif-msg"><?= htmlspecialchars($n['mensagem']) ?></div>
                        <div class="notif-time">
                            <i class="fa fa-clock-o"></i> 
                            <?= date('d/m/Y H:i', strtotime($n['criado_em'])) ?>
                        </div>
                        <?php if ($n['link']): ?>
                            <a href="<?= htmlspecialchars($n['link']) ?>" 
                               class="btn btn-nav btn-sm notif-link" 
                               data-id="<?= $n['id'] ?>"
                               style="margin-top: 1rem; display: inline-flex; border: 1px solid var(--border-color); color: var(--text-main);">
                                Ver Detalhes
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$n['lida']): ?>
                        <div class="notif-badge">Nova</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-notif">
            <div style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.3;">🔔</div>
            <h3>Não tens notificações</h3>
            <p>Avisaremos-te aqui quando houver novidades sobre as tuas选举.</p>
        </div>
    <?php endif; ?>
</div>


<script>
/**
 * Mark all notifications as read via AJAX
 */
async function markAllNotificationsRead() {
    const btn = document.getElementById('btnMarkAll');
    const originalText = btn.innerHTML;
    
    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> A processar...';

        const formData = new FormData();
        formData.append('action', 'mark_all');
        formData.append('csrf_token', getCSRFToken());

        const response = await fetch('api/notifications.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Update UI: remove unread classes and badges
            document.querySelectorAll('.notif-card.unread').forEach(card => {
                card.classList.remove('unread');
                const badge = card.querySelector('.notif-badge');
                if (badge) badge.style.display = 'none';
            });
            
            // Update header badge via global function if exists
            if (typeof updateBadgeUI === 'function') {
                updateBadgeUI(0);
            } else {
                const headerBadge = document.getElementById('notifBadge');
                if (headerBadge) headerBadge.style.display = 'none';
            }
            
            btn.innerHTML = '<i class="fa fa-check"></i> Concluído';
            setTimeout(() => {
                btn.style.display = 'none';
            }, 2000);
            
            showToast('Todas as notificações foram marcadas como lidas.', 'success');
        } else {
            throw new Error(data.message || 'Erro ao marcar notificações.');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

/**
 * Handle notification link clicks to mark individual as read before redirect
 */
document.querySelectorAll('.notif-link').forEach(link => {
    link.addEventListener('click', async (e) => {
        const notifId = link.getAttribute('data-id');
        const card = link.closest('.notif-card');
        
        if (card.classList.contains('unread')) {
            e.preventDefault();
            const originalHref = link.getAttribute('href');
            
            try {
                const formData = new FormData();
                formData.append('action', 'mark_read');
                formData.append('notification_id', notifId);
                formData.append('csrf_token', getCSRFToken());

                const response = await fetch('api/notifications.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                // Redirect regardless of success to ensure UX
                window.location.href = originalHref;
            } catch (err) {
                window.location.href = originalHref;
            }
        }
    });
});

// Helper to get CSRF token (fallback if not global)
function getCSRFToken() {
    return document.querySelector('input[name="csrf_token"]')?.value || 
           document.getElementById('csrf_token')?.value || 
           '<?= $_SESSION['csrf_token'] ?? '' ?>';
}
</script>

<?php require_once 'includes/footer.php'; ?>
