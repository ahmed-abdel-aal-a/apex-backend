<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/v1/admin/categories', 'GET');
$request->headers->set('Accept', 'application/json');
$catCtrl = app(App\Http\Controllers\CategoryController::class);
$cats = $catCtrl->index($request)->getData(true);
$prodCtrl = app(App\Http\Controllers\ProductController::class);
$prods = $prodCtrl->index($request)->getData(true);
echo "Category 0: " . json_encode($cats[0] ?? null) . "\n";
echo "Product 0: " . json_encode($prods['data'][0] ?? null) . "\n";
