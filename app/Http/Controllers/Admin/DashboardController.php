<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function summary()
    {
        $storeId = config('tenant.current_store_id', 1);
        abort_if(!$storeId, 403, 'Store ID is missing. Cannot process reports.');
        
        $stats = Cache::remember("dashboard_summary_stats_{$storeId}", 30, function () use ($storeId) {
            $now = Carbon::now();
            $currentMonth = $now->copy()->startOfMonth();
            $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
            $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();
            
            // Exclude cancelled, expired, and pending price confirmation from sales revenue
            $validSalesStatuses = fn($q) => $q->whereNotIn('status', ['cancelled', 'expired', 'pending_price_confirmation']);

            $totalSales = (float) Order::where($validSalesStatuses)->sum('total');
            $monthlySales = (float) Order::where($validSalesStatuses)
                                ->where('created_at', '>=', $currentMonth)
                                ->sum('total');
            $prevMonthSales = (float) Order::where($validSalesStatuses)
                                ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
                                ->sum('total');

            $totalOrders = Order::whereNotIn('status', ['cancelled', 'expired'])->count();
            $monthlyOrders = Order::whereNotIn('status', ['cancelled', 'expired'])
                                ->where('created_at', '>=', $currentMonth)
                                ->count();
            $prevMonthOrders = Order::whereNotIn('status', ['cancelled', 'expired'])
                                ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
                                ->count();

            // Real unique customers count (unique phone numbers from orders + registered accounts)
            $uniqueOrderBuyers = Order::whereNotIn('status', ['cancelled', 'expired'])
                                ->whereNotNull('customer_phone')
                                ->where('customer_phone', '!=', '')
                                ->distinct('customer_phone')
                                ->count('customer_phone');
            $registeredAccounts = Customer::where('is_guest', false)->count();
            $totalCustomersCount = max($uniqueOrderBuyers, $registeredAccounts, 1);

            $monthlyBuyers = Order::whereNotIn('status', ['cancelled', 'expired'])
                                ->where('created_at', '>=', $currentMonth)
                                ->whereNotNull('customer_phone')
                                ->distinct('customer_phone')
                                ->count('customer_phone');
            $prevMonthBuyers = Order::whereNotIn('status', ['cancelled', 'expired'])
                                ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd])
                                ->whereNotNull('customer_phone')
                                ->distinct('customer_phone')
                                ->count('customer_phone');

            // Trend calculation helper
            $calcTrend = function ($current, $previous) {
                if ($previous > 0) {
                    $change = round((($current - $previous) / $previous) * 100, 1);
                    return [
                        'trend' => ($change >= 0 ? '+' : '') . $change . '%',
                        'isUp' => $change >= 0,
                    ];
                }
                if ($current > 0) {
                    return ['trend' => '+100%', 'isUp' => true];
                }
                return ['trend' => '+0%', 'isUp' => true];
            };

            $salesTrend = $calcTrend($monthlySales, $prevMonthSales);
            $ordersTrend = $calcTrend($monthlyOrders, $prevMonthOrders);
            $customersTrend = $calcTrend($monthlyBuyers, $prevMonthBuyers);

            // Top Products based on actual sold order items
            $soldProducts = Product::select('products.id', 'products.name', 'products.price', 'products.images', 'categories.name as category')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNotIn('orders.status', ['cancelled', 'expired', 'pending_price_confirmation'])
                ->selectRaw('SUM(order_items.quantity) as sales_count, SUM(order_items.total) as total_revenue')
                ->groupBy('products.id', 'products.name', 'products.price', 'products.images', 'categories.name')
                ->orderByDesc('sales_count')
                ->take(5)
                ->get()
                ->map(function ($p) {
                    $images = is_array($p->images) ? $p->images : ($p->images ? json_decode($p->images, true) : []);
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'category' => $p->category ?? 'طباعة',
                        'price' => (float) $p->price,
                        'order_items_count' => (int) $p->sales_count,
                        'revenue' => (float) $p->total_revenue,
                        'image_url' => !empty($images[0]) ? $images[0] : null,
                    ];
                });

            // Fallback if no products have sales yet
            if ($soldProducts->isEmpty()) {
                $soldProducts = Product::select('products.id', 'products.name', 'products.price', 'products.images', 'categories.name as category')
                    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->take(5)
                    ->get()
                    ->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'name' => $p->name,
                            'category' => $p->category ?? 'عام',
                            'price' => (float) $p->price,
                            'order_items_count' => 0,
                            'revenue' => 0.0,
                            'image_url' => null,
                        ];
                    });
            }

            // Rich Recent Orders with Arabic friendly meta
            $recentOrders = Order::whereNotIn('status', ['cancelled', 'expired'])
                ->select(
                    'id', 'customer_name', 'customer_phone', 'total',
                    'status', 'payment_status', 'payment_method',
                    'shipping_address', 'items_count', 'created_at'
                )
                ->orderByDesc('created_at')
                ->limit(6)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name ?: 'عميل كاش',
                        'customer_phone' => $order->customer_phone ?: '-',
                        'total' => (float) $order->total,
                        'items_count' => (int) ($order->items_count ?: 1),
                        'status' => $order->status ?: 'pending',
                        'payment_status' => $order->payment_status ?: 'unpaid',
                        'payment_method' => $order->payment_method ?: 'cod',
                        'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
                        'relative_time' => $order->created_at ? $order->created_at->diffForHumans() : '',
                        'formatted_date' => $order->created_at ? $order->created_at->translatedFormat('d M Y - h:i A') : '',
                    ];
                });

            return [
                'total_sales' => (float)$totalSales,
                'total_sales_trend' => $salesTrend['trend'],
                'is_sales_up' => $salesTrend['isUp'],

                'monthly_sales' => (float)$monthlySales,
                'monthly_sales_trend' => $salesTrend['trend'],
                'is_monthly_sales_up' => $salesTrend['isUp'],

                'total_orders' => $totalOrders,
                'total_orders_trend' => $ordersTrend['trend'],
                'is_orders_up' => $ordersTrend['isUp'],

                'new_customers' => $totalCustomersCount,
                'new_customers_trend' => $customersTrend['trend'],
                'is_customers_up' => $customersTrend['isUp'],

                'top_products' => $soldProducts,
                'recent_orders' => $recentOrders,
            ];
        });

        return response()->json($stats);
    }
    
    public function reports(Request $request)
    {
        $range = $request->query('range', 'year');
        $storeId = config('tenant.current_store_id', 1);
        abort_if(!$storeId, 403, 'Store ID is missing. Cannot process reports.');
        
        $cacheKey = "dashboard_reports_{$storeId}_{$range}";

        // Cache for 60 seconds so reports are fresh yet light on the database
        $reportData = Cache::remember($cacheKey, 60, function () use ($range, $storeId) {
            $baseOrderQuery = Order::whereNotIn('status', ['cancelled', 'expired', 'pending_price_confirmation']);
            $now = Carbon::now();
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

            // Setup time windows and grouping
            if ($range === 'today') {
                $rangeStart = $now->copy()->startOfDay();
                $rangeEnd = $now->copy()->endOfDay();
                $prevStart = $now->copy()->subDay()->startOfDay();
                $prevEnd = $now->copy()->subDay()->endOfDay();
                $groupBy = $driver === 'sqlite' ? "strftime('%H:00', created_at)" : 'DATE_FORMAT(created_at, "%H:00")';
            } elseif ($range === 'week') {
                $rangeStart = $now->copy()->startOfWeek();
                $rangeEnd = $now->copy()->endOfWeek();
                $prevStart = $now->copy()->subWeek()->startOfWeek();
                $prevEnd = $now->copy()->subWeek()->endOfWeek();
                $groupBy = $driver === 'sqlite' ? "date(created_at)" : 'DATE(created_at)';
            } elseif ($range === 'month') {
                $rangeStart = $now->copy()->startOfMonth();
                $rangeEnd = $now->copy()->endOfMonth();
                $prevStart = $now->copy()->subMonth()->startOfMonth();
                $prevEnd = $now->copy()->subMonth()->endOfMonth();
                $groupBy = $driver === 'sqlite' ? "date(created_at)" : 'DATE(created_at)';
            } else { // year
                $rangeStart = $now->copy()->startOfYear();
                $rangeEnd = $now->copy()->endOfYear();
                $prevStart = $now->copy()->subYear()->startOfYear();
                $prevEnd = $now->copy()->subYear()->endOfYear();
                $groupBy = $driver === 'sqlite' ? "strftime('%m', created_at)" : 'DATE_FORMAT(created_at, "%m")';
            }

            // 1. Grouped Sales for Charts
            $salesRaw = (clone $baseOrderQuery)
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$groupBy} as label, SUM(total) as revenue, COUNT(*) as orders")
                ->groupBy('label')
                ->orderBy('label')
                ->get();

            if ($range === 'year') {
                $months = [
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                ];
                $salesMap = $salesRaw->keyBy(fn($item) => (int) $item->label);
                $sales = collect($months)->map(function ($name, $num) use ($salesMap) {
                    $found = $salesMap->get($num);
                    return [
                        'label' => $name,
                        'revenue' => $found ? (float) $found->revenue : 0.0,
                        'orders' => $found ? (int) $found->orders : 0,
                    ];
                })->values();
            } elseif ($range === 'week') {
                $salesMap = $salesRaw->keyBy(fn($item) => (string) $item->label);
                $days = [];
                $dayNames = [
                    'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الإثنين',
                    'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة'
                ];
                for ($i = 0; $i < 7; $i++) {
                    $day = $rangeStart->copy()->addDays($i);
                    $key = $day->toDateString();
                    $found = $salesMap->get($key);
                    $dayNameEn = $day->format('l');
                    $arName = $dayNames[$dayNameEn] ?? $dayNameEn;
                    $days[] = [
                        'label' => $arName . ' ' . $day->format('d/m'),
                        'revenue' => $found ? (float) $found->revenue : 0.0,
                        'orders' => $found ? (int) $found->orders : 0,
                    ];
                }
                $sales = collect($days);
            } elseif ($range === 'month') {
                $salesMap = $salesRaw->keyBy(fn($item) => (string) $item->label);
                $monthDays = [];
                for ($d = 1; $d <= $now->daysInMonth; $d++) {
                    $dateObj = $rangeStart->copy()->day($d);
                    $key = $dateObj->toDateString();
                    $found = $salesMap->get($key);
                    $monthDays[] = [
                        'label' => $d . ' ' . $dateObj->translatedFormat('M'),
                        'revenue' => $found ? (float) $found->revenue : 0.0,
                        'orders' => $found ? (int) $found->orders : 0,
                    ];
                }
                $sales = collect($monthDays);
            } else { // today
                $salesMap = $salesRaw->keyBy(fn($item) => (string) $item->label);
                $hours = [];
                for ($h = 0; $h < 24; $h += 2) {
                    $h1 = sprintf('%02d:00', $h);
                    $h2 = sprintf('%02d:00', $h + 1);
                    $f1 = $salesMap->get($h1);
                    $f2 = $salesMap->get($h2);
                    $rev = ($f1 ? (float)$f1->revenue : 0.0) + ($f2 ? (float)$f2->revenue : 0.0);
                    $ord = ($f1 ? (int)$f1->orders : 0) + ($f2 ? (int)$f2->orders : 0);
                    $hours[] = [
                        'label' => sprintf('%02d:00', $h),
                        'revenue' => $rev,
                        'orders' => $ord,
                    ];
                }
                $sales = collect($hours);
            }

            // 2. Top Products
            $productsQuery = Product::select('products.id', 'products.name', 'categories.name as category')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->join('order_items', 'products.id', '=', 'order_items.product_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereNotIn('orders.status', ['cancelled', 'expired', 'pending_price_confirmation'])
                ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
                ->selectRaw('SUM(order_items.quantity) as sales, SUM(order_items.total) as revenue')
                ->groupBy('products.id', 'products.name', 'categories.name')
                ->orderByDesc('revenue')
                ->limit(5);

            $products = $productsQuery->get();

            // Fallback if no sales in this specific short period
            if ($products->isEmpty()) {
                $products = Product::select('products.id', 'products.name', 'categories.name as category')
                    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->join('order_items', 'products.id', '=', 'order_items.product_id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->whereNotIn('orders.status', ['cancelled', 'expired', 'pending_price_confirmation'])
                    ->selectRaw('SUM(order_items.quantity) as sales, SUM(order_items.total) as revenue')
                    ->groupBy('products.id', 'products.name', 'categories.name')
                    ->orderByDesc('revenue')
                    ->limit(5)
                    ->get();
            }

            // Fallback to latest catalog products if store has zero completed orders
            if ($products->isEmpty()) {
                $products = Product::select('products.id', 'products.name', 'categories.name as category')
                    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                    ->limit(5)
                    ->get()
                    ->map(function ($p) {
                        $p->sales = 0;
                        $p->revenue = 0;
                        return $p;
                    });
            }

            $mappedProducts = $products->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category' => $p->category ?? 'عام',
                    'sales' => (int) ($p->sales ?? 0),
                    'revenue' => (float) ($p->revenue ?? 0),
                    'trend' => '+0%',
                    'isUp' => true,
                ];
            });

            // 3. Top Customers (Prioritize real buyers from orders and registered accounts)
            $orderCustomers = Order::whereNotIn('status', ['cancelled', 'expired', 'pending_price_confirmation'])
                ->whereNotNull('customer_name')
                ->where('customer_name', '!=', '')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->selectRaw('customer_name, customer_phone, MAX(customer_id) as customer_id, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('customer_name', 'customer_phone')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();

            // Fallback to top buyers overall if current filter has none
            if ($orderCustomers->isEmpty()) {
                $orderCustomers = Order::whereNotIn('status', ['cancelled', 'expired', 'pending_price_confirmation'])
                    ->whereNotNull('customer_name')
                    ->where('customer_name', '!=', '')
                    ->selectRaw('customer_name, customer_phone, MAX(customer_id) as customer_id, COUNT(*) as orders, SUM(total) as revenue')
                    ->groupBy('customer_name', 'customer_phone')
                    ->orderByDesc('revenue')
                    ->limit(5)
                    ->get();
            }

            if ($orderCustomers->isNotEmpty()) {
                $mappedCustomers = $orderCustomers->map(function ($oc, $idx) {
                    $spent = (float) ($oc->revenue ?? 0);
                    $tier = 'برونزي';
                    if ($spent >= 5000) $tier = 'بلاتيني';
                    elseif ($spent >= 2000) $tier = 'ذهبي';
                    elseif ($spent >= 500) $tier = 'فضي';

                    return [
                        'id' => $oc->customer_id ?? ($idx + 1),
                        'name' => $oc->customer_name,
                        'email' => $oc->customer_phone ?: '-',
                        'orders' => (int) ($oc->orders ?? 0),
                        'revenue' => $spent,
                        'loyaltyTier' => $tier,
                    ];
                });
            } else {
                // If no orders at all, look for registered non-guest customers who have orders
                $registeredCustomers = Customer::where('is_guest', false)
                    ->where(function ($q) {
                        $q->where('total_spent', '>', 0)->orWhere('total_orders', '>', 0);
                    })
                    ->whereNotNull('name')
                    ->where('name', '!=', '')
                    ->orderByDesc('total_spent')
                    ->limit(5)
                    ->get();

                $mappedCustomers = $registeredCustomers->map(function ($c) {
                    $spent = (float) ($c->total_spent ?? 0);
                    $tier = 'برونزي';
                    if ($spent >= 5000) $tier = 'بلاتيني';
                    elseif ($spent >= 2000) $tier = 'ذهبي';
                    elseif ($spent >= 500) $tier = 'فضي';

                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'email' => $c->email ?? ($c->phone ?? '-'),
                        'orders' => (int) ($c->total_orders ?? 0),
                        'revenue' => $spent,
                        'loyaltyTier' => $tier,
                    ];
                });
            }

            // 4. Cities Data
            $cityOrders = (clone $baseOrderQuery)->whereBetween('created_at', [$rangeStart, $rangeEnd]);
            if ($cityOrders->count() === 0) {
                $cityOrders = clone $baseOrderQuery;
            }

            $cityStats = [];
            foreach ($cityOrders->select('shipping_address', 'total')->cursor() as $order) {
                if (!$order->shipping_address) continue;
                
                $addressStr = (string) $order->shipping_address;
                $addressObj = json_decode($addressStr, true);
                
                $city = 'غير محدد';
                if (json_last_error() === JSON_ERROR_NONE && is_array($addressObj)) {
                    if (!empty($addressObj['city'])) {
                        $city = $addressObj['city'];
                    } elseif (!empty($addressObj['state'])) {
                        $city = $addressObj['state'];
                    }
                } else {
                    $parts = explode(',', $addressStr);
                    $city = trim($parts[0] ?? 'غير محدد');
                }
                
                if (empty($city)) {
                    $city = 'غير محدد';
                }

                if (!isset($cityStats[$city])) {
                    $cityStats[$city] = ['orders' => 0, 'revenue' => 0.0];
                }
                $cityStats[$city]['orders']++;
                $cityStats[$city]['revenue'] += (float) $order->total;
            }

            $citiesCollection = collect($cityStats)
                ->map(function ($stats, $name) {
                    return [
                        'id' => \Illuminate\Support\Str::slug($name) ?: ('city-' . md5($name)),
                        'name' => $name,
                        'orders' => (int) $stats['orders'],
                        'revenue' => (float) $stats['revenue'],
                    ];
                })
                ->sortByDesc('revenue')
                ->take(5)
                ->values();

            $totalCityRevenue = $citiesCollection->sum('revenue') ?: 1;
            $cities = $citiesCollection->map(function ($c) use ($totalCityRevenue) {
                $c['value'] = round(($c['revenue'] / $totalCityRevenue) * 100, 1);
                $c['growth'] = '+0%';
                $c['isUp'] = true;
                return $c;
            });

            // Fallback default cities if no city data found yet
            if ($cities->isEmpty()) {
                $cities = collect([
                    ['id' => 'riyadh', 'name' => 'الرياض', 'orders' => 0, 'revenue' => 0, 'value' => 0, 'growth' => '+0%', 'isUp' => true],
                    ['id' => 'jeddah', 'name' => 'جدة', 'orders' => 0, 'revenue' => 0, 'value' => 0, 'growth' => '+0%', 'isUp' => true],
                    ['id' => 'dammam', 'name' => 'الدمام', 'orders' => 0, 'revenue' => 0, 'value' => 0, 'growth' => '+0%', 'isUp' => true],
                    ['id' => 'makkah', 'name' => 'مكة المكرمة', 'orders' => 0, 'revenue' => 0, 'value' => 0, 'growth' => '+0%', 'isUp' => true],
                ]);
            }

            // 5. KPIs with comparative trend calculation
            $currentOrdersQuery = (clone $baseOrderQuery)->whereBetween('created_at', [$rangeStart, $rangeEnd]);
            $prevOrdersQuery = (clone $baseOrderQuery)->whereBetween('created_at', [$prevStart, $prevEnd]);

            $totalRevenue = (float) (clone $currentOrdersQuery)->sum('total');
            $totalOrders = (int) (clone $currentOrdersQuery)->count();
            $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.0;
            $newCustomers = Customer::whereBetween('created_at', [$rangeStart, $rangeEnd])->count();

            $prevRevenue = (float) (clone $prevOrdersQuery)->sum('total');
            $prevOrders = (int) (clone $prevOrdersQuery)->count();
            $prevAvg = $prevOrders > 0 ? round($prevRevenue / $prevOrders, 2) : 0.0;
            $prevNewCustomers = Customer::whereBetween('created_at', [$prevStart, $prevEnd])->count();

            $calcTrend = function ($current, $previous) {
                if ($previous > 0) {
                    $change = round((($current - $previous) / $previous) * 100, 1);
                    return [
                        'trend' => ($change >= 0 ? '+' : '') . $change . '%',
                        'isUp' => $change >= 0,
                    ];
                }
                if ($current > 0) {
                    return ['trend' => '+100%', 'isUp' => true];
                }
                return ['trend' => '+0%', 'isUp' => true];
            };

            $revTrend = $calcTrend($totalRevenue, $prevRevenue);
            $ordTrend = $calcTrend($totalOrders, $prevOrders);
            $avgTrend = $calcTrend($avgOrderValue, $prevAvg);
            $custTrend = $calcTrend($newCustomers, $prevNewCustomers);

            return [
                'sales' => $sales,
                'products' => $mappedProducts,
                'cities' => $cities,
                'customers' => $mappedCustomers,
                'kpis' => [
                    'revenue' => [ 'value' => (float)$totalRevenue, 'trend' => $revTrend['trend'], 'isUp' => $revTrend['isUp'] ],
                    'orders' => [ 'value' => $totalOrders, 'trend' => $ordTrend['trend'], 'isUp' => $ordTrend['isUp'] ],
                    'avgOrderValue' => [ 'value' => (float)$avgOrderValue, 'trend' => $avgTrend['trend'], 'isUp' => $avgTrend['isUp'] ],
                    'newCustomers' => [ 'value' => $newCustomers, 'trend' => $custTrend['trend'], 'isUp' => $custTrend['isUp'] ],
                ],
            ];
        });

        return response()->json($reportData);
    }
}
