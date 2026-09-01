<?php
$config = require __DIR__ . '/../config/bootstrap.php';

use App\Core\Logger;

$tail = 20;
$writeTest = false;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--tail=(\d+)$/', $arg, $m)) {
        $tail = max(1, min(200, (int)$m[1]));
    }
    if ($arg === '--write-test') {
        $writeTest = true;
    }
}

$file = Logger::currentLogFile();
$dir = dirname($file);

if ($writeTest) {
    Logger::warning(
        'Manual diagnostics write test.',
        ['event' => 'diagnostics_write_test'],
        'diagnostics'
    );
}

printf("Request ID: %s\n", Logger::requestId());
printf("Log level: %s\n", (string)($config['logging']['level'] ?? 'warning'));
printf("Retention: %d day(s)\n", (int)($config['logging']['retention_days'] ?? 30));
printf("Rotate after: %.1f MiB\n", ((int)($config['logging']['max_bytes'] ?? 26214400)) / 1048576);
printf("Directory: %s\n", $dir);
printf("Directory exists: %s\n", is_dir($dir) ? 'yes' : 'no');
printf("Directory writable: %s\n", is_dir($dir) && is_writable($dir) ? 'yes' : 'no');
printf("Current log: %s\n", $file);
printf("Current log exists: %s\n", is_file($file) ? 'yes' : 'no');

if (!is_file($file)) {
    echo "No current log entries yet. Use --write-test to verify the sink.\n";
    exit(0);
}

$lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$lines = array_slice($lines, -$tail);

echo "\nLast " . count($lines) . " entr" . (count($lines) === 1 ? 'y' : 'ies') . ":\n";
foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (!is_array($row)) {
        echo $line . "\n";
        continue;
    }

    printf(
        "%s %-8s %-18s %s request=%s\n",
        (string)($row['timestamp'] ?? ''),
        strtoupper((string)($row['level'] ?? '')),
        (string)($row['channel'] ?? ''),
        (string)($row['message'] ?? ''),
        (string)($row['request_id'] ?? '')
    );
}
