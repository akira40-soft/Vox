<?php
require_once 'config/helpers.php';
$stmt = $pdo->query("DESCRIBE comentarios");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "SCHEMA FOR 'comentarios':\n";
print_r($columns);
