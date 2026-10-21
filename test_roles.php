<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$user = App\Models\User::where('email', 'admin@apex-store.test')->first();
if (!$user) {
    echo "No admin user found.\n";
} else {
    try {
        echo 'Has Super Admin role? ' . ($user->hasRole('Super Admin') ? 'Yes' : 'No') . "\n";
    } catch (\Exception $e) {
        echo 'Has web? ERROR: ' . $e->getMessage() . "\n";
    }
}

