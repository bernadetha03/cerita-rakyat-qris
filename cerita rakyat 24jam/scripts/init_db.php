<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';

$attempts = 30;
for ($i=1; $i <= $attempts; $i++) {
    try {
        $pdo = db();
        $pdo->query('SELECT 1');
        break;
    } catch (Throwable $e) {
        if ($i === $attempts) {
            fwrite(STDERR, "Database belum dapat dihubungi: {$e->getMessage()}\n");
            exit(1);
        }
        fwrite(STDOUT, "Menunggu MySQL... percobaan {$i}/{$attempts}\n");
        sleep(2);
    }
}

$sql = file_get_contents(dirname(__DIR__).'/sql/schema.sql');
foreach (preg_split('/;\s*(?:\r?\n|$)/', (string)$sql) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') $pdo->exec($statement);
}

echo "Database siap.\n";
