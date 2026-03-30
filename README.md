# BookStack Backup

Backup management package for BookStack.

## Overview

This package adds a Backups area under settings and provides:

- Manual backup creation
- Backup history list with download and delete actions
- Delete confirmation page before file removal
- Backup settings for filename prefix, database include toggle, and max backups
- Automatic pruning by max backup count
- FTP-only remote settings support in backend and views

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
php artisan cache:clear
php artisan view:clear
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