<?php
require_once 'config/helpers.php';
try {
    // Create post_reacoes if missing
    $sql = "CREATE TABLE IF NOT EXISTS post_reacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        tipo ENUM('like', 'heart', 'clap', 'fire') DEFAULT 'like',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES campanhas(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reacao (post_id, user_id)
    ) ENGINE=InnoDB;";
    
    $pdo->exec($sql);
    echo "Tabela post_reacoes garantida.\n";

    // Ensure campanhas has the imagem column if missing (legacy check)
    $stmt = $pdo->query("SHOW COLUMNS FROM campanhas LIKE 'imagem'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE campanhas ADD COLUMN imagem VARCHAR(255) AFTER local");
        echo "Coluna 'imagem' adicionada a campanhas.\n";
    }

} catch (Exception $e) {
    echo "Erro ao atualizar schema: " . $e->getMessage();
}
