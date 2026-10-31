<?php

namespace Hibira\FilamentApprovals\Tests;

use Hibira\Filament\HibiraFilamentServiceProvider;
use Hibira\FilamentApprovals\ApprovalsServiceProvider;
use Hibira\FilamentApprovals\HibiraApprovals;
use Hibira\FilamentApprovals\Realtime;
use Hibira\FilamentApprovals\Tests\Fixtures\AdminPanelProvider;
use Hibira\FilamentApprovals\Tests\Fixtures\FakeRealtime;
use Hibira\FilamentApprovals\Tests\Fixtures\PendingStore;
use Hibira\FilamentApprovals\Tests\Fixtures\RefundAgent;
use Hibira\Laravel\HibiraServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Events\ToolApprovalRequested;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeRealtime $realtime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->realtime = new FakeRealtime;
        $this->app->instance(Realtime::class, $this->realtime);
        $this->app->singleton(ConversationStore::class, PendingStore::class);

        RefundAgent::$resumed = [];
        RefundAgent::$fails = null;
        PendingStore::$pending = [];
        HibiraApprovals::flush();
    }

    protected function getPackageProviders($app)
    {
        // Filament 3, 4 and 5 ship different packages; register the ones installed.
        $providers = array_filter([
            'BladeUI\\Heroicons\\BladeHeroiconsServiceProvider',
            'BladeUI\\Icons\\BladeIconsServiceProvider',
            'RyanChandler\\BladeCaptureDirective\\BladeCaptureDirectiveServiceProvider',
            'Livewire\\LivewireServiceProvider',
            'Filament\\Support\\SupportServiceProvider',
            'Filament\\Actions\\ActionsServiceProvider',
            'Filament\\Forms\\FormsServiceProvider',
            'Filament\\Infolists\\InfolistsServiceProvider',
            'Filament\\Notifications\\NotificationsServiceProvider',
            'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
            'Filament\\Schemas\\SchemasServiceProvider',
            'Filament\\Tables\\TablesServiceProvider',
            'Filament\\Widgets\\WidgetsServiceProvider',
            'Filament\\FilamentServiceProvider',
        ], 'class_exists');

        return array_merge(array_values($providers), [
            HibiraServiceProvider::class,
            AiServiceProvider::class,
            HibiraFilamentServiceProvider::class,
            ApprovalsServiceProvider::class,
            AdminPanelProvider::class,
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'https://shop.example');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('broadcasting.default', 'hibira');
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
        $app['config']->set('hibira.app_id', '1001');
        $app['config']->set('hibira.key', 'pk_test');
        $app['config']->set('hibira.secret', 'sk_test');
    }

    protected function defineRoutes($router)
    {
        Broadcast::routes(['middleware' => ['web']]);
    }

    protected function enablePush(): void
    {
        config(['hibira.push.instance_id' => 'push_test', 'hibira.push.secret_key' => 'push_secret']);
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/2026_10_31_000000_create_hibira_approvals_tables.php')->up();
    }

    /**
     * Pauses a RefundAgent turn the way laravel/ai does, and marks its calls
     * as still waiting in the conversation store.
     *
     * @param  list<PendingApproval>|null  $approvals
     */
    protected function pause(?object $user = null, ?array $approvals = null, string $invocation = 'inv_1', string $conversation = 'conv_1'): void
    {
        $approvals ??= [new PendingApproval('call_1', 'IssueRefund', ['order_id' => 4192, 'amount' => 4999], 'Refunds over $20 need a manager.')];

        PendingStore::$pending[$conversation] = $approvals;

        event(new ToolApprovalRequested($invocation, new RefundAgent, new Collection($approvals), $conversation, $user));
    }
}
