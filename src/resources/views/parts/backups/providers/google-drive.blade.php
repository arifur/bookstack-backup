<hr>
<h2 class="list-heading">{{ trans('bookstack-backup::settings.google_drive_heading') }}</h2>
<div class="grid half gap-xl">
    <div>
        <label class="setting-list-label">{{ trans('bookstack-backup::settings.google_drive_enabled') }}</label>
    </div>
    <div>
        @include('form.toggle-switch', ['name' => 'setting-backup-google-drive-enabled', 'value' => setting('backup-google-drive-enabled', false), 'label' => trans('bookstack-backup::settings.google_drive_enabled')])
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-google-drive-access-token" class="setting-list-label">{{ trans('bookstack-backup::settings.google_drive_access_token') }}</label>
    </div>
    <div>
        <input type="password" id="setting-backup-google-drive-access-token" name="setting-backup-google-drive-access-token" value="{{ setting('backup-google-drive-access-token', '') }}">
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-google-drive-folder-id" class="setting-list-label">{{ trans('bookstack-backup::settings.google_drive_folder_id') }}</label>
    </div>
    <div>
        <input type="text" id="setting-backup-google-drive-folder-id" name="setting-backup-google-drive-folder-id" value="{{ setting('backup-google-drive-folder-id', '') }}">
    </div>
</div>
