<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ProductAddon;
use App\Models\AddonGroup;
use App\Http\Requests\StoreProductAddonRequest;
use App\Http\Requests\UpdateProductAddonRequest;
use Illuminate\Http\Request;

class ProductAddonController extends Controller
{
    public function index(AddonGroup $addon_group)
    {
        return response()->json($addon_group->addons()->orderBy('sort_order')->get());
    }

    public function store(StoreProductAddonRequest $request, AddonGroup $addon_group)
    {
        $addon = $addon_group->addons()->create($request->validated());
        return response()->json($addon, 201);
    }

    public function show(AddonGroup $addon_group, ProductAddon $addon)
    {
        return response()->json($addon);
    }

    public function update(UpdateProductAddonRequest $request, AddonGroup $addon_group, ProductAddon $addon)
    {
        $addon->update($request->validated());
        return response()->json($addon);
    }

    public function destroy(AddonGroup $addon_group, ProductAddon $addon)
    {
        $addon->delete();
        return response()->json(null, 204);
    }
}
