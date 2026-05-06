<?php
/**
 * run_migrate_v3.php - Executa migração PostgreSQL para campos do wizard
 * Aceder via browser: https://vox-jo8t.onrender.com/run_migrate_v3.php
 * APAGAR DEPOIS de executar!
 */
require_once 'config/helpers.php';

// Segurança básica: apenas admin
$userId = requireAuth();
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    die('Acesso negado. Apenas administradores.');
}

$sqls = [
    "ALTER TABLE salas_eleitorais ADD COLUMN IF NOT EXISTS nome_organizacao VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE salas_eleitorais ADD COLUMN IF NOT EXISTS tipo_votacao_sala VARCHAR(50) DEFAULT 'maioria_simples'",
];

$results = [];
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['sql' => $sql, 'status' => '✅ OK'];
    } catch (PDOException $e) {
        $results[] = ['sql' => $sql, 'status' => '❌ ' . $e->getMessage()];
    }
}

// Verificar colunas atuais
$cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'salas_eleitorais' ORDER BY ordinal_position")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Vox - Migração v3</title>
<style>body{font-family:monospace;padding:2rem;background:#0f172a;color:#e2e8f0;} .ok{color:#10b981;} .err{color:#ef4444;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #334155;padding:0.5rem 1rem;text-align:left;} th{background:#1e293b;}</style>
</head>
<body>
<h2>🛠️ Vox - Migração v3: Campos do Wizard</h2>
<h3>Resultado das Migrações:</h3>
<ul>
<?php foreach ($results as $r): ?>
    <li class="<?= str_contains($r['status'], '✅') ? 'ok' : 'err' ?>">
        <strong><?= $r['status'] ?></strong><br>
        <code><?= htmlspecialchars($r['sql']) ?></code>
    </li>
<?php endforeach; ?>
</ul>

<h3>Colunas Atuais em <code>salas_eleitorais</code>:</h3>
<table>
    <tr><th>Coluna</th><th>Tipo</th></tr>
    <?php foreach ($cols as $c): ?>
    <tr><td><?= $c['column_name'] ?></td><td><?= $c['data_type'] ?></td></tr>
    <?php endforeach; ?>
</table>

<p style="margin-top:2rem;color:#f59e0b;">⚠️ <strong>APAGUE este ficheiro do servidor após confirmar que as migrações correram com sucesso!</strong></p>
</body>
</html>
