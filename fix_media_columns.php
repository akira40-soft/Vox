<?php
require_once 'config/db.php';
$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE campanhas ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) AFTER imagem");
    $pdo->exec("ALTER TABLE campanhas ADD COLUMN IF NOT EXISTS audio_url VARCHAR(255) AFTER video_url");
    echo "Colunas de mídia adicionadas com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro ao alterar tabela: " . $e->getMessage() . "\n";
}
