<?php
require_once 'config/helpers.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize($_POST['nome'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $usernameInput = sanitize($_POST['username'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $bi = sanitize($_POST['bi'] ?? '');
    $provincia = (int)($_POST['provincia'] ?? 0);
    $role = sanitize($_POST['role'] ?? 'eleitor');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($nome) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Preencha todos os campos obrigatórios.';
    } elseif ($provincia <= 0) {
        $error = 'Selecione uma Província.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas não coincidem.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        $biExists = false;
        if (!empty($bi)) {
            $stmtBi = $pdo->prepare("SELECT id FROM users WHERE bilhete_identidade = ?");
            $stmtBi->execute([$bi]);
            if ($stmtBi->fetch()) {
                $biExists = true;
            }
        }

        if ($stmt->fetch()) {
            $error = 'Este email já está registado.';
        } elseif ($biExists) {
            $error = 'Este Bilhete de Identidade já está associado a outra conta.';
        } else {
            $verificationToken = bin2hex(random_bytes(16));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Process username
            $usernameFinal = null;
            if (!empty($usernameInput)) {
                $usernameFinal = preg_replace('/\s+/', '', strtolower($usernameInput));
                // Check if username exists
                $stmtU = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtU->execute([$usernameFinal]);
                if ($stmtU->fetch()) {
                    $error = "O nome de utilizador '@{$usernameFinal}' já está em uso.";
                }
            }

            if (!$error) {
                // Apenas organizadores precisam de aprovação
                $estado = ($role === 'organizador') ? 'pendente' : 'ativo';

                $stmt = $pdo->prepare("
                    INSERT INTO users (nome_completo, username, email, telefone, bilhete_identidade, provincia_id,
                        password, role, estado, token_verificacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([$nome, $usernameFinal, $email, $telefone, $bi, $provincia, $hashedPassword, $role, $estado, $verificationToken]);

                $userId = $pdo->lastInsertId();
                $pdo->exec("UPDATE estatisticas_sistema SET valor = valor + 1 WHERE metrica = 'total_usuarios'");
                auditLog($userId, 'registo', "Novo utilizador: $nome ($email)");

                // Mensagem diferente dependendo do tipo de conta
                if ($role === 'organizador') {
                    setFlash('success', 'Conta criada com sucesso! Aguarde aprovação do administrador para criar salas eleitorais.');
                } else {
                    setFlash('success', 'Conta criada com sucesso! Pode entrar no sistema agora.');
                }
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Erro: O Email ou Bilhete de Identidade já está registado no sistema.';
                } else {
                    $error = 'Ocorreu um erro no servidor ao tentar registar. Tente novamente.';
                }
            }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Vox | Junte-se à Revolução Democrática</title>
    
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
            background: radial-gradient(circle at bottom right, #f0f9ff, #ffffff);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-900);
            padding: 2rem 1.5rem;
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
            max-width: 650px;
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
            margin-bottom: 2rem;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0.5rem;
        }

        .form-group.full { grid-column: span 2; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-700);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            background: var(--gray-50);
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
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
            margin-top: 1.5rem;
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
            text-align: center;
        }

        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
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
        }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: span 1; }
            .auth-card { padding: 2rem; }
            .back-home { top: 1rem; left: 1rem; }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-home">← Voltar</a>

    <div class="auth-card">
        <a href="index.php" class="logo">🗳️ Vox</a>
        
        <h1>Criar Minha Conta Vox</h1>
        <p class="subtitle">Junte-se à plataforma eleitoral mais moderna de Angola.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="registo.php" method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" placeholder="Isaac Quarenta" required>
                </div>

                <div class="form-group">
                    <label>Nome de Utilizador (@username)</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="ex: isaac.q" style="text-transform:lowercase;">
                </div>
                
                <div class="form-group">
                    <label>E-mail Corporativo</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="exemplo@vox.ao" required>
                </div>

                <div class="form-group">
                    <label>Telefone / WhatsApp</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" placeholder="923 000 000">
                </div>

                <div class="form-group">
                    <label>Bilhete de Identidade (BI)</label>
                    <input type="text" name="bi" value="<?= htmlspecialchars($_POST['bi'] ?? '') ?>" placeholder="Opcional">
                </div>

                <div class="form-group">
                    <label>Província (Angola)</label>
                    <select name="provincia" required>
                        <option value="">Selecione...</option>
                        <?php
                        $stmt = $pdo->query("SELECT id, nome FROM provincias ORDER BY nome");
                        while ($p = $stmt->fetch()):
                        ?>
                            <option value="<?= $p['id'] ?>" <?= (isset($_POST['provincia']) && $_POST['provincia'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Objetivo na Plataforma</label>
                    <select name="role" required>
                        <option value="eleitor" <?= (isset($_POST['role']) && $_POST['role'] === 'eleitor') ? 'selected' : '' ?>>Eleitor / Candidato (Votar e participar em campanhas)</option>
                        <option value="organizador" <?= (isset($_POST['role']) && $_POST['role'] === 'organizador') ? 'selected' : '' ?>>Organizador (Criar e organizar eleições - Requer aprovação)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Palavra-passe</label>
                    <input type="password" name="password" placeholder="Mín. 6 caracteres" required>
                </div>

                <div class="form-group">
                    <label>Confirmar Password</label>
                    <input type="password" name="confirm_password" placeholder="Repita a password" required>
                </div>
            </div>

            <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 1.5rem; text-align: center; line-height: 1.5;">
                Ao registar-se, concorda com nossos <a href="#" style="color: var(--primary);">Termos de Serviço</a> e <a href="#" style="color: var(--primary);">Política de Privacidade</a> de Angola 🇦🇴.
            </div>

            <button type="submit" class="btn-submit">Criar Minha Conta</button>
        </form>

        <div class="bottom-links">
            Já tem uma conta? <a href="login.php">Entrar Agora</a>
        </div>
    </div>

</body>
</html>

