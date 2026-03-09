<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sample = App\Models\ReviewEditLog::where('actor_role', 'reviewer')
    ->latest('id')
    ->take(10)
    ->get(['id','review_id','actor_id','actor_name','actor_role','created_at'])
    ->toArray();

echo json_encode([
    'review_edit_logs' => App\Models\ReviewEditLog::count(),
    'reviewer_logs' => App\Models\ReviewEditLog::where('actor_role', 'reviewer')->count(),
    'sample' => $sample,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
