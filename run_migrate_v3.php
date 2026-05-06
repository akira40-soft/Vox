<?php
/**
 * run_migrate_v3.php - PostgreSQL Schema Migration v3
 * Adiciona colunas: nome_organizacao, tipo_votacao_sala
 * Executar UMA VEZ via browser: https://vox-jo8t.onrender.com/run_migrate_v3.php
 */
require_once 'config/db.php';
$pdo = getDB();

$migrations = [
    "ALTER TABLE salas_eleitorais ADD COLUMN IF NOT EXISTS nome_organizacao VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE salas_eleitorais ADD COLUMN IF NOT EXISTS tipo_votacao_sala VARCHAR(50) DEFAULT 'maioria_simples'",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['ok', $sql];
    } catch (PDOException $e) {
        $results[] = ['err', $e->getMessage() . ' | SQL: ' . $sql];
    }
}

// Verificar colunas actuais
$cols = $pdo->query("
    SELECT column_name, data_type, column_default 
    FROM information_schema.columns 
    WHERE table_name = 'salas_eleitorais' 
    ORDER BY ordinal_position
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Vox — Migração v3</title>
<style>
  body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
  h2   { color: #60a5fa; }
  .ok  { color: #34d399; }
  .err { color: #f87171; }
  table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
  th, td { border: 1px solid #334155; padding: 0.5rem 1rem; text-align: left; }
  th { background: #1e293b; color: #94a3b8; }
  .highlight { background: rgba(59,130,246,0.1); font-weight: bold; }
</style>
</head>
<body>
<h2>🛠️ Vox — Migração PostgreSQL v3</h2>

<h3>Resultado:</h3>
<ul>
<?php foreach ($results as [$status, $msg]): ?>
    <li class="<?= $status ?>">
        [<?= strtoupper($status) ?>] <?= htmlspecialchars($msg) ?>
    </li>
<?php endforeach; ?>
</ul>

<h3>Colunas actuais em <code>salas_eleitorais</code>:</h3>
<table>
    <tr><th>Coluna</th><th>Tipo</th><th>Default</th></tr>
    <?php foreach ($cols as $c): ?>
    <tr class="<?= in_array($c['column_name'], ['nome_organizacao','tipo_votacao_sala']) ? 'highlight' : '' ?>">
        <td><?= htmlspecialchars($c['column_name']) ?></td>
        <td><?= htmlspecialchars($c['data_type']) ?></td>
        <td><?= htmlspecialchars($c['column_default'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p style="margin-top:2rem; color:#f59e0b;">
  ⚠️ Migração completa. Pode fechar esta página.
</p>
</body>
</html>
