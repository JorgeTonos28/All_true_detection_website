<?php

// Additive local upgrade with a consistent private backup; never a production deployment.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
$expected = realpath(database_path('database.sqlite'));
if (PHP_SAPI !== 'cli' || ! $app->environment('local') || $connection->getDriverName() !== 'sqlite'
    || ! $expected || realpath($connection->getDatabaseName()) !== $expected) {
    throw new RuntimeException('Este helper solo actualiza la SQLite local habitual del proyecto.');
}
$allowed = ['2026_11_04_000001_add_account_types_catalog_and_bank_accounts', '2026_11_04_000002_index_order_creation_dates', '2026_11_04_000003_refresh_customer_service_catalog', '2026_11_04_000004_align_default_catalog_copy'];
$ran = $app->make('migration.repository')->getRan();
$pending = array_values(array_diff(array_keys($app->make('migrator')->getMigrationFiles(database_path('migrations'))), $ran));
if (! $pending) {
    echo "Esquema comercial local actualizado. No se modificó la base.\n";
    exit(0);
}
if (array_diff($pending, $allowed)) {
    throw new RuntimeException('Hay migraciones ajenas a esta actualización; revisarlas por separado.');
}
if (! in_array('--apply', $argv, true)) {
    echo 'Verificado: SQLite local exacta, '.count($pending)." migraciones comerciales aditivas pendientes. Use --apply para respaldar y aplicar.\n";
    exit(0);
}
$directory = storage_path('app/private/operational-backups');
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('No se pudo preparar el respaldo privado.');
}
$backup = $directory.'/before-business-'.now()->format('Ymd-His').'-'.Str::random(8).'.sqlite';
$connection->unprepared('VACUUM INTO '.$connection->getPdo()->quote($backup));
$snapshot = new PDO('sqlite:'.$backup);
if ($snapshot->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
    throw new RuntimeException('La copia consistente no pasó la verificación de integridad.');
}
$tables = ['users', 'orders', 'order_people', 'order_items', 'order_attachments', 'order_results', 'invoices', 'payments', 'identity_sessions'];
$digest = static function (PDO $pdo, string $table): string {
    $hash = hash_init('sha256');
    $rows = $pdo->query('SELECT * FROM '.$table.' ORDER BY id');
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
    }

    return hash_final($hash);
};
$before = [];
foreach ($tables as $table) {
    $before[$table] = $digest($snapshot, $table);
}
$accountCount = $connection->table('customer_accounts')->count();
$exit = Artisan::call('migrate', ['--path' => array_map(fn ($name) => 'database/migrations/'.$name.'.php', $pending), '--force' => true]);
echo Artisan::output();
if ($exit !== 0 || $connection->selectOne('PRAGMA integrity_check')->integrity_check !== 'ok' || $connection->select('PRAGMA foreign_key_check')) {
    throw new RuntimeException('Revise la actualización y conserve la copia privada.');
}
foreach ($before as $table => $hash) {
    if (! hash_equals($hash, $digest($connection->getPdo(), $table))) {
        throw new RuntimeException('Cambió una tabla protegida; revise actividad concurrente y la copia privada.');
    }
}
if ($connection->table('customer_accounts')->count() !== $accountCount) {
    throw new RuntimeException('Cambió el número de cuentas; revise actividad concurrente.');
}
echo json_encode(['backup' => $backup, 'integrity' => 'ok', 'protected_tables_unchanged' => count($tables), 'customer_accounts_preserved' => true], JSON_THROW_ON_ERROR).PHP_EOL;
