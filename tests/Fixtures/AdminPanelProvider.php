<?php

namespace Hibira\FilamentApprovals\Tests\Fixtures;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Hibira\Filament\HibiraPlugin;
use Hibira\FilamentApprovals\HibiraApprovalsPlugin;
use Hibira\FilamentApprovals\Models\ApprovalRequest;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->pages([Dashboard::class])
            ->plugins([
                HibiraPlugin::make()->databaseNotifications(),
                HibiraApprovalsPlugin::make()
                    // Interns may look, but not at refunds.
                    ->authorize(fn (User $user, ?ApprovalRequest $request) => $user->email !== 'intern@example.com'
                        || $request === null || ! $request->calls->contains('tool', 'IssueRefund'))
                    ->approvers(fn () => User::query()->where('email', '!=', 'intern@example.com')->get())
                    ->databaseNotifications(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
