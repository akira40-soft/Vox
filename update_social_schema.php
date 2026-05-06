<?php
require_once 'config/helpers.php';

try {
    // 1. Tabela de Seguidores
    $pdo->exec("CREATE TABLE IF NOT EXISTS seguidores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seguidor_id INT NOT NULL,
        seguido_id INT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_follow (seguidor_id, seguido_id),
        FOREIGN KEY (seguidor_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seguido_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 2. Tabela de Retweets
    $pdo->exec("CREATE TABLE IF NOT EXISTS retweets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_retweet (post_id, user_id),
        FOREIGN KEY (post_id) REFERENCES campanhas(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Tabela de Mensagens Diretas (DMs)
    $pdo->exec("CREATE TABLE IF NOT EXISTS mensagens_diretas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sala_id INT NOT NULL,
        remetente_id INT NOT NULL,
        destinatario_id INT NOT NULL,
        conteudo TEXT NOT NULL,
        lida TINYINT(1) DEFAULT 0,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sala_id) REFERENCES salas_eleitorais(id) ON DELETE CASCADE,
        FOREIGN KEY (remetente_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (destinatario_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Tabelas criadas com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro de BD: " . $e->getMessage() . "\n";
}
