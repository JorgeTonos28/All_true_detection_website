<?php

// Explicit, additive local upgrade. Never connects to a non-local/non-SQLite database.
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
    throw new RuntimeException('Solo la SQLite local habitual puede actualizarse con este helper.');
}
$migration = '2026_10_28_000003_add_identity_partner_credit_access';
$ran = $app->make('migration.repository')->getRan();
$pending = array_values(array_diff(array_keys($app->make('migrator')->getMigrationFiles(database_path('migrations'))), $ran));
if ($pending === []) {
    echo "Esquema local actualizado; no se modificó la base.\n";
    exit(0);
}
if ($pending !== [$migration]) {
    throw new RuntimeException('Hay otras migraciones pendientes. Revisarlas antes de actualizar.');
}
if (! in_array('--apply', $argv, true)) {
    echo "Verificado: APP_ENV local, SQLite exacta y única migración aditiva pendiente. Use --apply para respaldar y aplicar.\n";
    exit(0);
}
$directory = storage_path('app/private/operational-backups');
if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    throw new RuntimeException('No se pudo crear la carpeta privada de respaldos.');
}
$backup = $directory.'/before-identity-credit-'.now()->format('Ymd-His').'-'.Str::random(8).'.sqlite';
$connection->unprepared('VACUUM INTO '.$connection->getPdo()->quote($backup));
$snapshot = new PDO('sqlite:'.$backup);
if ($snapshot->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
    throw new RuntimeException('La copia no pasó la verificación de integridad.');
}
$tables = ['users', 'customer_accounts', 'orders', 'order_people', 'order_attachments', 'order_results', 'invoices', 'payments', 'identity_sessions'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = (int) $snapshot->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
}
$exit = Artisan::call('migrate', ['--path' => 'database/migrations/'.$migration.'.php']);
echo Artisan::output();
if ($exit !== 0 || $connection->selectOne('PRAGMA integrity_check')->integrity_check !== 'ok') {
    throw new RuntimeException('Revisar migración/integridad; conservar copia privada.');
}
foreach ($counts as $table => $count) {
    if ($connection->table($table)->count() !== $count) {
        throw new RuntimeException('Cambió un conteo; revisar actividad concurrente y la copia privada.');
    }
}
if ($connection->table('customer_accounts')->where('identity_api_enabled', true)->exists()) {
    throw new RuntimeException('Revisar autorizaciones: este upgrade no debe aprobar partners.');
}
echo json_encode(['backup' => $backup, 'integrity' => 'ok', 'counts_preserved' => $counts, 'partners_enabled' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
