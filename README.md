# BookStack Backup

Backup management package for BookStack.

## Overview

This package adds a Backups area under settings and provides:

- Manual backup creation
- Backup history list with download and delete actions
- Delete confirmation page before file removal
- Backup settings for filename prefix, database include toggle, and max backups
- Automatic pruning by max backup count
- FTP remote upload settings for manual and scheduled backups

Access is protected by the settings-manage permission.

## Installation

Add the package to BookStack:

```json
"require": {
    "arifur/bookstack-backup": "dev-main"
},
"repositories": [
    {
        "type": "path",
        "url": "../bookstack-backup"
    }
]
```

Then run:

```bash
composer update
php artisan package:discover
php artisan cache:clear
php artisan view:clear
php artisan migrate
```

If you prefer publishing package migrations first:

```bash
php artisan vendor:publish --provider="Arifur\\BookstackBackup\\BackupServiceProvider" --tag="bookstack-backup-migrations"
php artisan migrate
```

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag="bookstack-backup-config"
```

Current config keys:

```php
return [
    'storage_path' => storage_path('backups'),
    'max_backups' => 10,
];
```

## Routes

All routes are registered by the package and use middleware web, auth, and can:settings-manage.

| Method | URI | Purpose |
|---|---|---|
| GET | /settings/backups | Backup page |
| POST | /settings/backups | Update backup settings |
| GET | /settings/backups/backup-settings | Backup settings page |
| POST | /settings/backups/backup-settings | Save backup settings page values |
| POST | /settings/backups/create | Create backup now |
| GET | /settings/backups/download/{filename} | Download backup |
| GET | /settings/backups/delete/{filename} | Delete confirmation page |
| DELETE | /settings/backups/delete/{filename} | Delete backup |
| GET | /settings/backups/schedule | Schedule page endpoint |
| POST | /settings/backups/schedule | Schedule save endpoint |
| GET | /settings/backups/remote | Remote page endpoint |
| POST | /settings/backups/remote | Remote save endpoint |

Note: Current sidebar navigation shows Backup and Backup Settings. Schedule and Remote routes exist but are not shown in the sidebar by default.

## Features

- Manual backup creation (database and/or uploads)
- Backup history with download, hash, and delete
- Delete confirmation page before file removal
- Backup settings for filename prefix, database include toggle, and max backups
- Automatic pruning by max backup count
- FTP remote upload for manual and scheduled backups
- **Enable Remote Backup**: master toggle to globally enable/disable all remote uploads
- Remote upload progress indicator on manual backup
- Schedule backups via UI (requires cron or Laravel scheduler)
- Notification email field is currently disabled in the UI

## Running Scheduled Backups

You can run scheduled backups using either of these approaches.

### Option A: Direct Cron Command (Without Laravel Scheduler)

Run the package command directly from cron:

```
* * * * * cd /path/to/your/project && php artisan bookstack-backup:run-scheduled >> /dev/null 2>&1
```

### Option B: Laravel Scheduler

Run Laravel's scheduler from cron, then let the app invoke the package schedule:

```
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/project` with your BookStack project root (where `artisan` is located).

## Configuring Scheduled Backups

To set up and configure scheduled backups:

1. **Install and publish the package config (if not already done):**

   ```bash
   composer update
   php artisan vendor:publish --tag="bookstack-backup-config"
   php artisan cache:clear
   php artisan view:clear
   ```

2. **Configure your backup schedule in the BookStack UI:**
   - Go to **Settings → Backups → Schedule**.
   - Enable scheduled backups using the toggle.
   - Set your preferred schedule:
     - **Frequency**: Choose daily, weekly, or monthly.
     - **Time**: Set the time of day for the backup to run.
     - **Day of Week**: (If weekly) Select which day backups should run.
     - **Day of Month**: (If monthly) Select which day backups should run.
   - (Optional) Toggle whether to keep a local copy after remote upload.
   - Save your changes.

3. **Set up one of the scheduler execution options:**

   Edit your crontab:
   ```bash
   crontab -e
   ```

    Option A (direct package command):
    ```
    * * * * * cd /path/to/your/project && php artisan bookstack-backup:run-scheduled >> /dev/null 2>&1
    ```

    Option B (Laravel scheduler):
    ```
    * * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
    ```

    Both options should run every minute.

**Note:**
- Use one scheduler execution option to avoid duplicate runs.
- The notification email field is currently disabled in the UI.

## Notes

- Backups are created as zip files.
- History currently shows date-only in list rows, with full filename and full created timestamp on hover.
- Delete action is a two-step flow through a confirmation page.

## Troubleshooting

Backups fail to create:

1. Ensure mysqldump is installed and available on PATH.
2. Verify DB credentials in your environment.
3. Ensure the backup storage path is writable.

Package not appearing:

1. Run composer update.
2. Clear view and app caches.
3. Check logs in storage/logs.

Migrations not applied:

1. Run `php artisan package:discover`.
2. Run `php artisan migrate`.
3. If needed, publish migrations with `php artisan vendor:publish --provider="Arifur\\BookstackBackup\\BackupServiceProvider" --tag="bookstack-backup-migrations"` then run `php artisan migrate` again.

## Publishing Assets

Publish views:

```bash
php artisan vendor:publish --provider="Arifur\\BookstackBackup\\BackupServiceProvider" --tag="bookstack-backup-views"
```

Publish translations:

```bash
php artisan vendor:publish --provider="Arifur\\BookstackBackup\\BackupServiceProvider" --tag="bookstack-backup-lang"
```

## License

MIT