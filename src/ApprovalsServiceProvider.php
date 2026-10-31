<?php

namespace Hibira\FilamentApprovals;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\ToolApprovalRequested;
use Laravel\Ai\Events\ToolApprovalResolved;
use Throwable;

class ApprovalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hibira-approvals.php', 'hibira-approvals');
        $this->app->singleton(Realtime::class);
        $this->app->singleton(ApprovalDesk::class);
        $this->app->singleton(Resumer::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hibira-approvals');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'hibira-approvals');

        // The agent's own request must never fail because the inbox did.
        Event::listen(ToolApprovalRequested::class, function (ToolApprovalRequested $event) {
            try {
                $this->app->make(ApprovalDesk::class)->record($event);
            } catch (Throwable $e) {
                report($e);
            }
        });

        Event::listen(ToolApprovalResolved::class, function (ToolApprovalResolved $event) {
            try {
                $this->app->make(ApprovalDesk::class)->resolved($event);
            } catch (Throwable $e) {
                report($e);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hibira-approvals.php' => config_path('hibira-approvals.php'),
            ], 'hibira-approvals-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/hibira-approvals'),
            ], 'hibira-approvals-translations');
        }
    }
}
