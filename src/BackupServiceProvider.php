<?php

namespace Arifur\BookstackBackup;

use Arifur\BookstackBackup\Console\Commands\RunScheduledBackupCommand;
use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/backups.php',
            'backups'
        );
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunScheduledBackupCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('bookstack-backup:run-scheduled')->everyMinute()->withoutOverlapping();
            });
        }

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/resources/views', 'bookstack-backup');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/lang', 'bookstack-backup');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        // Inject backup menu item into the settings navbar
        $this->injectSettingsNavItem();

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/backups.php' => config_path('backups.php'),
        ], 'bookstack-backup-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/database/migrations' => database_path('migrations'),
        ], 'bookstack-backup-migrations');

        // Publish views
        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views/vendor/bookstack-backup'),
        ], 'bookstack-backup-views');

        // Publish translations
        $this->publishes([
            __DIR__.'/lang' => resource_path('lang/vendor/bookstack-backup'),
        ], 'bookstack-backup-lang');
    }

    /**
     * Use BookStack's theme event system to inject the backup menu item
     * into the settings navbar without modifying core files.
     */
    private function injectSettingsNavItem(): void
    {
        Theme::listen(ThemeEvents::WEB_MIDDLEWARE_AFTER, function (Request $request, $response) {
            // Only inject on settings pages
            if (!$request->is('settings', 'settings/*')) {
                return null;
            }

            // Only process HTML responses
            if (!str_contains($response->headers->get('content-type', ''), 'text/html')) {
                return null;
            }

            if (!method_exists($response, 'getContent') || !method_exists($response, 'setContent')) {
                return null;
            }

            $content = $response->getContent();

            // Locate the settings sidebar nav by its unique class
            $navMarker = 'class="active-link-list';
            $navStart = strpos($content, $navMarker);
            if ($navStart === false) {
                return null;
            }

            // Find the closing </nav> tag after the nav start
            $navEnd = strpos($content, '</nav>', $navStart);
            if ($navEnd === false) {
                return null;
            }

            // Render the backup nav item with correct active state
            $selected = $request->is('settings/backups') ? 'backups' : '';
            $backupItem = view('bookstack-backup::settings.parts.injected-nav-item', ['selected' => $selected])->render();

            // Insert the backup item just before the closing </nav>
            $newContent = substr_replace($content, $backupItem, $navEnd, 0);
            $response->setContent($newContent);

            return $response;
        });
    }
}

