<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Place;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

$svc = app(\App\Services\OsmImportService::class);

echo "Places total: " . Place::count() . PHP_EOL;
echo "Jobs: " . DB::table('jobs')->count() . " total, " . DB::table('jobs')->whereNull('reserved_at')->count() . " pending" . PHP_EOL;

echo "--- District statuses ---" . PHP_EOL;
foreach ($svc->districts() as $d) {
    $s = $svc->getStatus($d);
    $status = $s['status'] ?? 'unknown';
    $objs = $s['object_count'] ?? '';
    echo "  {$d}: {$status} ({$objs})" . PHP_EOL;
}