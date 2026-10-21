<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Customer;

Broadcast::routes(['prefix' => 'api', 'middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.Customer.{id}', function (Customer $customer, $id) {
    return (int) $customer->id === (int) $id;
});
