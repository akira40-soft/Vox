<?php
/**
 * run_migrate_v2.php — Execute schema migration for Vox V2.0
 * Access once via browser: http://localhost/Projeto-elitoral/run_migrate_v2.php
 */
require_once 'config/helpers.php';

// Only admins can run this
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    // Allow CLI execution
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        die('<h2>Acesso negado. Apenas administradores podem executar migrações.</h2>');
    }
}

$sql = file_get_contents(__DIR__ . '/sql/migrate_v2.sql');
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with(ltrim($s), '--')
);

$results = [];
foreach ($statements as $stmt) {
    if (empty(trim($stmt))) continue;
    try {
        $pdo->exec($stmt);
        $results[] = ['ok', substr(trim($stmt), 0, 80) . '...'];
    } catch (PDOException $e) {
        $results[] = ['err', $e->getMessage()];
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt">
<head><title>Migração V2.0 — Vox</title>
<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
.ok  { color: #34d399; } .err { color: #f87171; }
h2   { color: #60a5fa; margin-bottom: 1.5rem; }
li   { margin-bottom: 0.5rem; font-size: 0.9rem; }
</style></head>
<body>
<h2>🏛️ Vox V2.0 — Resultado da Migração</h2>
<ul>
<?php foreach ($results as [$status, $msg]): ?>
    <li class="<?= $status ?>">
        [<?= strtoupper($status) ?>] <?= htmlspecialchars($msg) ?>
    </li>
<?php endforeach; ?>
</ul>
<hr style="border-color:#334155; margin: 2rem 0;">
<p style="color:#94a3b8;">Migração concluída. Pode fechar esta página e apagar <code>run_migrate_v2.php</code>.</p>
</body></html>
