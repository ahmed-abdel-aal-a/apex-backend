<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
// use App\Observers\OrderObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            // Super Admin passes all checks, EXCEPT we don't want them to delete themselves or another Super Admin.
            // The UserPolicy's explicitly returned false values might be overridden if we return true here.
            // To ensure UserPolicy's `delete` is respected, we only grant implicit true for other abilities.
            // Wait, Laravel's Gate::before returning true overrides everything.
            // Let's just return true for Super Admin, and we will hardcode the protection in the Controller to be safe.
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Order::observe(OrderObserver::class);
        \App\Models\Offer::observe(\App\Observers\OfferObserver::class);
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\PrintingType::observe(\App\Observers\PrintingTypeObserver::class);
        
        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'product' => \App\Models\Product::class,
            'category' => \App\Models\Category::class,
            'printing_type' => \App\Models\PrintingType::class,
        ]);
        

        RateLimiter::for('chat-messages', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });
    }
}
