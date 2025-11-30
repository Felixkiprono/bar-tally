<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\User;
use App\Models\Meter;
use App\Models\Invoice;
use App\Models\MeterAssignment;
use App\Models\Payment;
use App\Models\MeterReading;
use App\Policies\StrictPolicy;
use App\Policies\MeterReaderPolicy;
use App\Policies\MeterReadingPolicy;
use App\Policies\MeterPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        User::class => StrictPolicy::class,
        Meter::class => MeterPolicy::class,
        Invoice::class => StrictPolicy::class,
        Payment::class => StrictPolicy::class,
        MeterReading::class => MeterReadingPolicy::class,

    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
