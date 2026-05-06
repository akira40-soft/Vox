<?php
/**
 * perfil.php - Vox Electoral Platform
 * User profile: view, update info, change password
 */
require_once 'config/helpers.php';
$loggedInUser = requireAuth();

// Determine which profile to view (default to self)
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : $loggedInUser;
$isOwner = ($viewId === (int)$loggedInUser);

// Fetch viewed user data
$stmt = $pdo->prepare("SELECT u.*, p.nome as provincia_nome FROM users u LEFT JOIN provincias p ON u.provincia_id = p.id WHERE u.id = ?");
$stmt->execute([$viewId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Utilizador não encontrado.');
    redirect('dashboard.php');
}

$erroInfo = null;
$erroPass = null;
$sucessoInfo = null;
$sucessoPass = null;

// Fetch provinces for select
$stmt = $pdo->query("SELECT id, nome FROM provincias ORDER BY nome");
$provincias = $stmt->fetchAll();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $nomeCompleto = sanitize($_POST['nome_completo'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $provinciaId = (int)($_POST['provincia_id'] ?? 0);
    $usernameInput = sanitize($_POST['username'] ?? '');

    // Basic validation
    if (empty($nomeCompleto)) {
        $erroInfo = 'O nome é obrigatório.';
    } else {
        // Handle username rules (no spaces, unique)
        $usernameInput = preg_replace('/\s+/', '', strtolower($usernameInput)); // force lowercase, no spaces
        
        // Check uniqueness if changed
        $canUpdate = true;
        if (!empty($usernameInput) && $usernameInput !== $user['username']) {
            $uCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $uCheck->execute([$usernameInput, $loggedInUser]);
            if ($uCheck->fetch()) {
                $erroInfo = "O username '@{$usernameInput}' já está em uso.";
                $canUpdate = false;
            }
        }

        if ($canUpdate) {
            $stmt = $pdo->prepare("
                UPDATE users SET nome_completo = ?, telefone = ?, provincia_id = ?, username = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nomeCompleto, 
                $telefone, 
                $provinciaId > 0 ? $provinciaId : null, 
                empty($usernameInput) ? null : $usernameInput,
                $loggedInUser
            ]);

            $_SESSION['user_nome'] = $nomeCompleto;

            auditLog($loggedInUser, 'perfil_atualizado', "Perfil atualizado: $nomeCompleto");
            $sucessoInfo = 'Perfil atualizado com sucesso!';

            $stmt = $pdo->prepare("SELECT u.*, p.nome as provincia_nome FROM users u LEFT JOIN provincias p ON u.provincia_id = p.id WHERE u.id = ?");
            $stmt->execute([$loggedInUser]);
            $user = $stmt->fetch();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (empty($senhaAtual)) {
        $erroPass = 'Insira a senha atual.';
    } elseif (empty($novaSenha)) {
        $erroPass = 'Insira a nova senha.';
    } elseif (strlen($novaSenha) < 6) {
        $erroPass = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erroPass = 'As senhas nao coincidem.';
    } elseif (!password_verify($senhaAtual, $user['password'])) {
        $erroPass = 'A senha atual esta incorreta.';
    } else {
        $hashedPassword = password_hash($novaSenha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPassword, $loggedInUser]);

        auditLog($loggedInUser, 'senha_alterada', 'Senha alterada com sucesso');
        $sucessoPass = 'Senha alterada com sucesso!';
    }
}

$pageTitle = 'Meu Perfil';
require 'includes/header.php';
?>

<div class="dashboard-content">
    <div class="profile-page">

        <div class="dashboard-panel" style="padding: 2.5rem; margin-bottom: 2rem; background: linear-gradient(135deg, var(--blue-deeper) 0%, var(--blue-dark) 100%); color: white; border: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: white;"><?= $isOwner ? 'Meu Perfil' : 'Perfil de Utilisador' ?></h2>
                    <p style="opacity: 0.8; font-size: 1.1rem;"><?= $isOwner ? 'Gira as suas informações pessoais e segurança da conta.' : 'Ficha pública do cidadão na plataforma Vox.' ?></p>
                </div>
                <a href="dashboard.php" class="btn btn-primary" style="background: white; color: var(--blue-deeper); border: none; font-weight: 800; padding: 0.75rem 1.5rem;">Voltar ao Painel</a>
            </div>
        </div>

        <div class="dashboard-panel" style="padding: 2.5rem; margin-bottom: 2rem;">
            <div class="profile-header" style="border-bottom: 1px solid var(--gray-100); padding-bottom: 2rem; margin-bottom: 2rem;">
                <div class="profile-avatar-lg" style="width: 100px; height: 100px; font-size: 2.5rem; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);"><?= strtoupper(substr($user['nome_completo'], 0, 1)) ?></div>
                <div style="margin-left: 0.5rem;">
                    <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--gray-900); margin-bottom: 0.25rem;"><?= htmlspecialchars($user['nome_completo']) ?></h2>
                    <?php if (!empty($user['username'])): ?>
                    <div style="font-weight: 700; color: var(--primary); font-size: 1.1rem; margin-bottom: 0.5rem;">@<?= htmlspecialchars($user['username']) ?></div>
                    <?php endif; ?>
                    <p style="font-size: 1.1rem; color: var(--gray-500); margin-bottom: 1rem;"><?= htmlspecialchars($user['email']) ?></p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <span class="badge badge-<?= htmlspecialchars($user['role']) ?>" style="padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700;"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                        <span class="badge badge-<?= htmlspecialchars($user['estado']) ?>" style="padding: 0.5rem 1rem; border-radius: 999px; font-weight: 700;"><?= htmlspecialchars(ucfirst($user['estado'])) ?></span>
                        <?php if ($user['provincia_nome']): ?>
                        <span style="background: var(--gray-100); color: var(--gray-600); padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600;">📍 <?= htmlspecialchars($user['provincia_nome']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($erroInfo): ?>
                <div class="alert alert-error"><?= htmlspecialchars($erroInfo) ?></div>
            <?php endif; ?>
            <?php if ($sucessoInfo): ?>
                <div class="alert alert-success"><?= htmlspecialchars($sucessoInfo) ?></div>
            <?php endif; ?>

            </form>

            <?php if ($isOwner): ?>
            <!-- Password and sensitive forms only for owner -->
            <form class="profile-form" method="POST" style="margin-top: 3rem; border-top: 1px solid var(--gray-100); padding-top: 2rem;">
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">👤</span> Informações Pessoais
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome_completo">Nome Completo</label>
                        <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($user['nome_completo']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Nome de Utilizador (@username)</label>
                        <div style="display:flex; align-items:center;">
                            <span style="background:var(--gray-200); padding:0.75rem 1rem; border-radius:0.5rem 0 0 0.5rem; color:var(--gray-600); font-weight:bold; border:1px solid var(--gray-300); border-right:none;">@</span>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" placeholder="ex: oteunome" style="border-radius:0 0.5rem 0.5rem 0; flex-grow:1; text-transform:lowercase;">
                        </div>
                        <small style="color:var(--gray-500); display:block; margin-top:0.25rem;">Usado para seres identificado nas campanhas (sem espaços).</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Endereço de Email</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--gray-50); color:var(--gray-500); cursor: not-allowed; border-color: var(--gray-200);">
                    <small style="color:var(--gray-500); display: block; margin-top: 0.25rem;">O email não pode ser alterado por motivos de segurança.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone">Telefone / Contacto</label>
                        <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" placeholder="+244 9XX XXX XXX">
                    </div>

                    <div class="form-group">
                        <label for="provincia_id">Província de Residência</label>
                        <select id="provincia_id" name="provincia_id">
                            <option value="">Selecione a sua província</option>
                            <?php foreach ($provincias as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ((int)($user['provincia_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bi">Número do B.I.</label>
                    <input type="text" id="bi" value="<?= htmlspecialchars($user['bilhete_identidade'] ?? '') ?>" disabled style="background:var(--gray-50); color:var(--gray-500); cursor: not-allowed; border-color: var(--gray-200);">
                </div>

                <button type="submit" name="atualizar_perfil" class="btn btn-primary" style="margin-top: 1rem; padding: 0.75rem 2rem; font-weight: 700;">Atualizar Dados do Perfil</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($isOwner): ?>
        <div class="dashboard-panel" style="padding: 2.5rem; margin-top: 2rem;">
            <?php if ($erroPass): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($erroPass) ?></div>
            <?php endif; ?>
            <?php if ($sucessoPass): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= htmlspecialchars($sucessoPass) ?></div>
            <?php endif; ?>

            <form class="profile-form" method="POST">
                <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.5rem;">🔒</span> Alterar Senha de Acesso
                </h3>

                <div class="form-group">
                    <label for="senha_atual">Senha Atual</label>
                    <input type="password" id="senha_atual" name="senha_atual" required placeholder="••••••••">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nova_senha">Nova Senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" required minlength="6" placeholder="Pelo menos 6 caracteres">
                    </div>

                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Nova Senha</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6" placeholder="Confirme a nova senha">
                    </div>
                </div>

                <button type="submit" name="alterar_senha" class="btn btn-warning" style="margin-top: 1rem; padding: 0.75rem 2rem; font-weight: 700;">Definir Nova Senha</button>
            </form>
        </div>

        <!-- Convites Pendentes Panel -->
        <div class="dashboard-panel" style="padding: 2.5rem; margin-top: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">📩</span> Convites de Candidatura <span id="invites-badge" style="display:none; background:var(--danger); color:white; padding:0.1rem 0.6rem; border-radius:9999px; font-size:0.8rem;">0</span>
            </h3>
            <div id="invites-list">
                <p style="color:var(--gray-500);text-align:center;margin-top:1rem;">A verificar convites...</p>
            </div>
        </div>

        <div class="dashboard-panel" style="padding: 2.5rem; margin-top: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">🕒</span> Histórico de Atividades
            </h3>
            <?php
            $stmt = $pdo->prepare("
                SELECT * FROM auditoria WHERE user_id = ? ORDER BY criado_em DESC LIMIT 10
            ");
            $stmt->execute([$loggedInUser]);
            $userAudit = $stmt->fetchAll();

            if (!empty($userAudit)):
            ?>
            <div class="activity-table" style="margin-top:1rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Acao</th>
                            <th>Detalhes</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($userAudit as $a): ?>
                    <tr>
                        <td><span class="badge badge-info"><?= htmlspecialchars($a['acao']) ?></span></td>
                        <td><?= htmlspecialchars($a['detalhes'] ?? '') ?></td>
                        <td><?= formatDate($a['criado_em']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:var(--gray-500);text-align:center;margin-top:1rem;">Sem atividade recente.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if ($isOwner): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    loadMyInvites();
});

async function loadMyInvites() {
    try {
        const res = await fetch('api/users.php?action=my_invites');
        const data = await res.json();
        
        const list = document.getElementById('invites-list');
        const badge = document.getElementById('invites-badge');
        
        if (data.success) {
            const invites = data.invites;
            if (invites.length > 0) {
                badge.textContent = invites.length;
                badge.style.display = 'inline-block';
                
                list.innerHTML = invites.map(i => `
                    <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:1rem; padding:1.5rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <div style="font-size:0.85rem; color:var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Convite para Candidatura</div>
                            <h4 style="font-size:1.1rem; font-weight:800; margin:0; color:var(--gray-900);">Eleição: ${i.sala_nome}</h4>
                            <div style="font-size:0.85rem; color:var(--gray-500); margin-top:0.25rem;">Recebido a ${new Date(i.criado_em).toLocaleDateString('pt-PT')}</div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button onclick="handleInvite('${i.token_convite}', 'decline', this)" class="btn" style="background:var(--gray-200); color:var(--gray-700); font-weight:700;">Recusar</button>
                            <button onclick="handleInvite('${i.token_convite}', 'accept', this)" class="btn" style="background:#10b981; color:white; font-weight:700;">Aceitar Candidatura</button>
                        </div>
                    </div>
                `).join('');
            } else {
                badge.style.display = 'none';
                list.innerHTML = '<p style="color:var(--gray-500);text-align:center;margin-top:1rem;">Não tens convites pendentes.</p>';
            }
        }
    } catch(e) { console.error('Erro ao carregar convites', e); }
}

async function handleInvite(token, action, btnEl) {
    btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    btnEl.disabled = true;
    
    const fd = new FormData();
    fd.append('action', action);
    fd.append('token', token);
    
    try {
        const res = await fetch('api/users.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            alert(data.message);
            if (action === 'accept' && data.sala_id) {
                window.location.href = `sala_detalhes.php?id=${data.sala_id}`;
            } else {
                loadMyInvites();
            }
        } else {
            alert(data.message || 'Erro ao processar convite.');
            btnEl.disabled = false;
            btnEl.textContent = action === 'accept' ? 'Aceitar' : 'Recusar';
        }
    } catch(e) {
        alert('Erro de conexão.');
        btnEl.disabled = false;
        btnEl.textContent = action === 'accept' ? 'Aceitar' : 'Recusar';
    }
}
</script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
