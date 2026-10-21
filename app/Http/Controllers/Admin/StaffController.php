<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Admin\StaffStoreRequest;
use App\Http\Requests\Admin\StaffUpdateRequest;
use App\Http\Resources\Admin\UserResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StaffController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', User::class);
        $staff = User::with('roles')->get();
        return UserResource::collection($staff);
    }

    public function store(StaffStoreRequest $request)
    {
        $this->authorize('create', User::class);
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['roles']);

        return new UserResource($user->load('roles'));
    }

    public function show(User $staff)
    {
        $this->authorize('view', $staff);
        return new UserResource($staff->load('roles'));
    }

    public function update(StaffUpdateRequest $request, User $staff)
    {
        $this->authorize('update', $staff);
        
        // Strict protection against modifying Super Admin roles
        if ($staff->hasRole('Super Admin') && in_array('roles', array_keys($request->validated()))) {
            abort(403, 'Cannot modify Super Admin roles.');
        }

        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $staff->update($validated);

        if (isset($validated['roles'])) {
            $staff->syncRoles($validated['roles']);
        }

        return new UserResource($staff->load('roles'));
    }

    public function destroy(User $staff)
    {
        $this->authorize('delete', $staff);

        // Strict protection against deleting Super Admin
        if ($staff->hasRole('Super Admin')) {
            abort(403, 'Cannot delete a Super Admin.');
        }

        $staff->delete();

        return response()->noContent();
    }
}
