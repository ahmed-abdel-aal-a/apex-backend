<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AddonGroup;
use App\Http\Requests\StoreAddonGroupRequest;
use App\Http\Requests\UpdateAddonGroupRequest;
use Illuminate\Http\Request;

class AddonGroupController extends Controller
{
    public function index()
    {
        return response()->json(AddonGroup::with('addons')->orderBy('sort_order')->get());
    }

    public function store(StoreAddonGroupRequest $request)
    {
        $group = AddonGroup::create($request->validated());
        return response()->json($group->load('addons'), 201);
    }

    public function show($id)
    {
        $group = AddonGroup::with('addons')->findOrFail($id);
        return response()->json($group);
    }

    public function update(UpdateAddonGroupRequest $request, $id)
    {
        $group = AddonGroup::findOrFail($id);
        $group->update($request->validated());
        return response()->json($group->load('addons'));
    }

    public function destroy($id)
    {
        $group = AddonGroup::findOrFail($id);
        $group->delete();
        return response()->json(null, 204);
    }
}
