<?php
require_once __DIR__ . '/../connection.php';

$tables = ['muscle_groups', 'muscles', 'exercises'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
        echo $t . ": " . $count . PHP_EOL;
    } catch (Throwable $e) {
        echo $t . ": ERROR " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "muscle_groups (id, name):" . PHP_EOL;
try {
    $rows = $pdo->query("SELECT id, name FROM muscle_groups ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo $r['id'] . " " . $r['name'] . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "ERROR " . $e->getMessage() . PHP_EOL;
}
