<?php

namespace App\Providers;

use App\Models\Letterhead;
use App\Policies\LetterheadPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Aggiungi questa riga
        Letterhead::class => LetterheadPolicy::class,

        // ... altre policy esistenti ...
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate personalizzati se necessari
        Gate::define('manage-letterheads', function ($user) {
            return in_array($user->user_type, ['super_admin', 'national_admin', 'zone_admin']);
        });
    }
}
