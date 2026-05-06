<?php
// Debug 500 error
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/helpers.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Preencha todos os campos.';
    } else {
        // Procura por usuário com email, independentemente do estado
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Email ou senha incorretos.';
            auditLog(null, 'login_falhou', "Tentativa de login com email: $email");
        } elseif ($user['estado'] === 'banido') {
            $error = 'A sua conta foi banida.';
            auditLog(null, 'login_bloqueado', "Tentativa de login com conta banida: $email");
        } elseif ($user['estado'] === 'pendente' && $user['role'] === 'organizador') {
            $error = 'A sua conta de organizador está pendente de aprovação pelo administrador. Poderá entrar quando for aprovada.';
            auditLog(null, 'login_pendente', "Tentativa de login com conta organizador pendente: $email");
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Email ou senha incorretos.';
            auditLog(null, 'login_falhou', "Tentativa de login com email: $email");
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome_completo'];
            $_SESSION['user_role'] = $user['role'];

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
            }

            auditLog($user['id'], 'login_sucesso', "Login: {$user['nome_completo']}");
            $redir = $_SESSION['redirect_after_login'] ?? 'home.php';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redir");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceder à Conta - Vox | Painel de Votação</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-900: #111827;
            --white: #ffffff;
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --radius-lg: 1.25rem;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: radial-gradient(circle at top left, #f0f9ff, #ffffff);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-900);
            padding: 1.5rem;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 3.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-2xl);
            width: 100%;
            max-width: 480px;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-bottom: 2.5rem;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        p.subtitle {
            color: var(--gray-500);
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-900);
        }

        .form-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            background: var(--gray-50);
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .bottom-links {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .bottom-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            text-decoration: none;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 480px) {
            .auth-card { padding: 2rem; }
            .back-home { top: 1rem; left: 1rem; }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-home">← Voltar</a>

    <div class="auth-card">
        <a href="index.php" class="logo">🗳️ Vox</a>
        
        <h1>Bem-vindo de volta</h1>
        <p class="subtitle">Insira suas credenciais para aceder ao painel.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logout'): ?>
            <div class="alert alert-success">
                Sessão terminada com sucesso em Angola 🇦🇴
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label>E-mail Corporativo</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="exemplo@vox.ao" required>
            </div>
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label style="margin-bottom: 0;">Palavra-passe</label>
                    <a href="contactos.php" style="font-size: 0.8rem; color: var(--primary); text-decoration: none;">Esqueceu-se?</a>
                </div>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem; font-size: 0.85rem;">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember" style="font-weight: 500; cursor: pointer;">Lembrar-me neste dispositivo</label>
            </div>

            <button type="submit" class="btn-submit">Entrar no Painel</button>
        </form>

        <div class="bottom-links">
            Ainda não tem conta? <a href="registo.php">Criar Agora</a>
        </div>
    </div>

</body>
</html>

