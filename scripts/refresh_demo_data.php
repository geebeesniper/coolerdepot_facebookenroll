<?php

$config = require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$sql = file_get_contents(dirname(__DIR__) . '/database/demo.sql');

if ($sql === false) {
    fwrite(STDERR, "Could not read database/demo.sql\n");
    exit(1);
}

$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

foreach ($statements as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    $pdo->exec($statement);
}

echo "Demo data refreshed to v0.1.55
";
