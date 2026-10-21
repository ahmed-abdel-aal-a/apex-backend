$req = Illuminate\Http\Request::create('/api/v1/admin/products', 'GET');
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$res = $kernel->handle($req);
echo 'Status: ' . $res->getStatusCode() . PHP_EOL;
echo substr($res->getContent(), 0, 500);
