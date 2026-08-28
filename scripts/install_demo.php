<?php
$config = require dirname(__DIR__) . '/config/bootstrap.php';
use App\Core\Database;

$sql = file_get_contents(dirname(__DIR__) . '/database/demo.sql');
if ($sql === false) {
    exit("Could not read demo.sql\n");
}

$pdo = Database::connection();
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}
echo "Demo data installed.\n";
