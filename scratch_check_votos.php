<?php
require_once __DIR__ . '/config/helpers.php';
$pdo = getDB();
$stmt = $pdo->query('DESCRIBE votos');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
