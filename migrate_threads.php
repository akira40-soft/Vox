<?php
require_once 'config/helpers.php';
try {
    $pdo->exec("ALTER TABLE comentarios ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER user_id");
    $pdo->exec("ALTER TABLE comentarios ADD CONSTRAINT fk_parent_comment FOREIGN KEY (parent_id) REFERENCES comentarios(id) ON DELETE CASCADE");
    echo "SUCCESS: parent_id added to comentarios table.";
} catch (Exception $e) {
    echo "ERROR or INFO: " . $e->getMessage();
}
