<?php

/**
 * Copia todas las tablas (estructura + datos) de la base local definida en .env
 * a una base MySQL en Railway.
 *
 * Uso (PowerShell, desde la raíz del proyecto):
 *   $env:RAILWAY_DB_PASSWORD = '...'
 *   php scripts/migrar-a-railway.php            # falla si el destino ya tiene tablas
 *   php scripts/migrar-a-railway.php --force    # borra las tablas existentes en el destino
 *
 * Variables opcionales: RAILWAY_DB_HOST, RAILWAY_DB_PORT, RAILWAY_DB_USER, RAILWAY_DB_NAME
 */

$force = in_array('--force', $argv, true);

$env = parseEnv(__DIR__ . '/../.env');

$local = [
    'host' => $env['DB_HOST'] ?? '127.0.0.1',
    'port' => $env['DB_PORT'] ?? '3306',
    'db'   => $env['DB_DATABASE'] ?? 'sofompro',
    'user' => $env['DB_USERNAME'] ?? 'root',
    'pass' => $env['DB_PASSWORD'] ?? '',
];

$remote = [
    'host' => getenv('RAILWAY_DB_HOST') ?: 'sakura.proxy.rlwy.net',
    'port' => getenv('RAILWAY_DB_PORT') ?: '26129',
    'db'   => getenv('RAILWAY_DB_NAME') ?: 'railway',
    'user' => getenv('RAILWAY_DB_USER') ?: 'root',
    'pass' => getenv('RAILWAY_DB_PASSWORD'),
];

if (! $remote['pass']) {
    fail('Define la contraseña de Railway en la variable de entorno RAILWAY_DB_PASSWORD.');
}

$src = connect($local);
$dst = connect($remote);

info("Origen:  {$local['user']}@{$local['host']}:{$local['port']}/{$local['db']}");
info("Destino: {$remote['user']}@{$remote['host']}:{$remote['port']}/{$remote['db']} (MySQL " . $dst->query('SELECT VERSION()')->fetchColumn() . ')');

$tables = $src->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
if (! $tables) {
    fail('La base local no tiene tablas.');
}

$existing = $dst->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
if ($existing && ! $force) {
    fail('El destino ya tiene ' . count($existing) . ' tablas (' . implode(', ', array_slice($existing, 0, 5)) . "...).\n"
        . 'Vuelve a correr con --force para borrarlas y reemplazarlas.');
}

$dst->exec('SET FOREIGN_KEY_CHECKS = 0');
$dst->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");

foreach ($existing as $table) {
    $dst->exec('DROP TABLE IF EXISTS ' . q($table));
}

// 1) Estructura
foreach ($tables as $table) {
    $ddl = $src->query('SHOW CREATE TABLE ' . q($table))->fetch(PDO::FETCH_NUM)[1];
    $dst->exec(toMysql($ddl));
}
info('Estructura creada: ' . count($tables) . ' tablas.');

// 2) Datos
$totalRows = 0;
foreach ($tables as $table) {
    $columns = $src->query('SHOW COLUMNS FROM ' . q($table))->fetchAll(PDO::FETCH_COLUMN);
    $colList = implode(', ', array_map('q', $columns));
    $batchSize = max(1, min(500, intdiv(60000, count($columns))));
    $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

    $stmt = $src->query('SELECT ' . $colList . ' FROM ' . q($table), PDO::FETCH_NUM);
    $copied = 0;
    $batch = [];

    $flush = function () use (&$batch, &$copied, $dst, $table, $colList, $rowPlaceholder) {
        if (! $batch) {
            return;
        }
        $sql = 'INSERT INTO ' . q($table) . " ($colList) VALUES "
            . implode(', ', array_fill(0, count($batch), $rowPlaceholder));
        $dst->prepare($sql)->execute(array_merge(...$batch));
        $copied += count($batch);
        $batch = [];
    };

    $dst->beginTransaction();
    foreach ($stmt as $row) {
        $batch[] = $row;
        if (count($batch) >= $batchSize) {
            $flush();
        }
    }
    $flush();
    $dst->commit();

    $totalRows += $copied;
    printf("  %-35s %6d filas\n", $table, $copied);
}

$dst->exec('SET FOREIGN_KEY_CHECKS = 1');

// 3) Verificación
$mismatches = [];
foreach ($tables as $table) {
    $a = (int) $src->query('SELECT COUNT(*) FROM ' . q($table))->fetchColumn();
    $b = (int) $dst->query('SELECT COUNT(*) FROM ' . q($table))->fetchColumn();
    if ($a !== $b) {
        $mismatches[] = "$table (local $a, railway $b)";
    }
}

if ($mismatches) {
    fail("Conteos distintos en: \n  " . implode("\n  ", $mismatches));
}

info("Listo: " . count($tables) . " tablas y $totalRows filas copiadas, conteos verificados.");

// ---------------------------------------------------------------------------

function connect(array $c): PDO
{
    try {
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]
        );
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    } catch (PDOException $e) {
        fail("No se pudo conectar a {$c['host']}:{$c['port']}/{$c['db']}: " . $e->getMessage());
    }
}

/** Ajusta el DDL de MariaDB para MySQL 8/9. */
function toMysql(string $ddl): string
{
    // Columnas JSON de MariaDB: longtext ... CHECK (json_valid(`col`)) -> json
    $ddl = preg_replace(
        '/longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin( NOT NULL| DEFAULT NULL)?( DEFAULT NULL)? CHECK \(json_valid\(`[^`]+`\)\)/i',
        'json$1$2',
        $ddl
    );

    return $ddl;
}

function q(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function parseEnv(string $path): array
{
    if (! is_file($path)) {
        fail("No se encontró $path");
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $vars[trim($key)] = trim(trim($value), "\"'");
    }

    return $vars;
}

function info(string $msg): void
{
    echo $msg, PHP_EOL;
}

function fail(string $msg): never
{
    fwrite(STDERR, "ERROR: $msg" . PHP_EOL);
    exit(1);
}
