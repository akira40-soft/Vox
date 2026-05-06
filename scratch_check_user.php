<?php
require_once 'config/helpers.php';
$userId = requireAuth();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$dbRole = $stmt->fetchColumn();

echo "<h1>Debug Info</h1>";
echo "Session User ID: " . $userId . "<br>";
echo "Session Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "<br>";
echo "Database Role: " . ($dbRole ?: 'USER NOT FOUND') . "<br>";
?>
