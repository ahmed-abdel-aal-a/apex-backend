<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerBrowsingHistory;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerBrowsingHistoryResource;
use App\Http\Requests\CustomerFilterRequest;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CustomerFilterRequest $request)
    {
        $query = Customer::query();

        // The TenantScope handles store_id automatically

        $search = $request->validated('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($source = $request->validated('source')) {
            $query->whereRaw('LOWER(source) = ?', [strtolower($source)]);
        }

        if ($device = $request->validated('device')) {
            $query->whereRaw('LOWER(device) = ?', [strtolower($device)]);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($minSpent = $request->validated('min_spent')) {
            $query->where('total_spent', '>=', $minSpent);
        }

        if ($maxSpent = $request->validated('max_spent')) {
            $query->where('total_spent', '<=', $maxSpent);
        }

        if ($dateFrom = $request->validated('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sort = $request->validated('sort');
        if ($sort === 'top_spenders') {
            $query->orderBy('total_spent', 'desc');
        } elseif ($sort === 'oldest') {
            $query->oldest('created_at');
        } else {
            $query->latest('created_at'); // latest
        }

        $customers = $query->paginate(15)->withQueryString();
        
        return CustomerResource::collection($customers);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        // UUIDs don't need regex stripping
        $customer = Customer::with(['browsingHistory' => function ($query) {
            $query->take(100);
        }])->findOrFail($id);

        $realSessions = $customer->calculateSessionsCount();
        if ($realSessions > ($customer->visits_count ?? 1)) {
            $customer->update(['visits_count' => $realSessions]);
            $customer->visits_count = $realSessions;
        }

        return new CustomerResource($customer);
    }

    /**
     * Display a paginated history for a specific customer.
     */
    public function history(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);

        $history = CustomerBrowsingHistory::where('customer_id', $customer->id)
            ->latest() // defaults to created_at
            ->paginate(20);

        return CustomerBrowsingHistoryResource::collection($history);
    }

    /**
     * Get aggregated traffic acquisition source statistics.
     */
    public function sourceStats(Request $request)
    {
        $stats = Customer::query()
            ->selectRaw("
                COALESCE(LOWER(source), 'direct') as source,
                COUNT(*) as total_visitors,
                SUM(CASE WHEN total_orders > 0 THEN 1 ELSE 0 END) as buyers_count,
                COALESCE(SUM(total_spent), 0) as total_revenue
            ")
            ->groupByRaw("COALESCE(LOWER(source), 'direct')")
            ->orderByDesc('total_visitors')
            ->get();

        $totalAll = (int) $stats->sum('total_visitors');
        if ($totalAll === 0) {
            return response()->json([
                'total_customers' => 0,
                'sources' => []
            ]);
        }

        $data = $stats->map(function ($item) use ($totalAll) {
            $visitors = (int) $item->total_visitors;
            return [
                'source' => (string) $item->source,
                'visitors' => $visitors,
                'buyers' => (int) $item->buyers_count,
                'revenue' => (float) $item->total_revenue,
                'percentage' => round(($visitors / $totalAll) * 100, 1),
            ];
        });

        return response()->json([
            'total_customers' => $totalAll,
            'sources' => $data
        ]);
    }
}
