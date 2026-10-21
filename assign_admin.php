<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'test@example.com')->first();
if ($user) {
    $user->assignRole('Super Admin');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo "SUCCESS: Super Admin role assigned to " . $user->email . PHP_EOL;
} else {
    echo "ERROR: User not found" . PHP_EOL;
}
