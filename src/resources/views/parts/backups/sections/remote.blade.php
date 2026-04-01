<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_remote_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_remote_description') }}</p>

<form action="{{ route('backups.remote.update') }}" method="POST">
    {!! csrf_field() !!}
    <div class="setting-list">
        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_remote_enabled') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_remote_enabled_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-remote-enabled', 'value' => setting('backup-remote-enabled', true), 'label' => trans('bookstack-backup::settings.backup_remote_enabled')])
            </div>
        </div>

        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_remote_upload_on_create') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_remote_upload_on_create_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-remote-upload-on-create', 'value' => setting('backup-remote-upload-on-create', false), 'label' => trans('bookstack-backup::settings.backup_remote_upload_on_create')])
            </div>
        </div>

        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_remote_upload_on_schedule') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_remote_upload_on_schedule_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-remote-upload-on-schedule', 'value' => setting('backup-remote-upload-on-schedule', false), 'label' => trans('bookstack-backup::settings.backup_remote_upload_on_schedule')])
            </div>
        </div>

        @include('bookstack-backup::parts.backups.providers.ftp')
    </div>

    <div class="form-group text-right">
        <button type="submit" class="button">{{ trans('bookstack-backup::settings.settings_save') }}</button>
    </div>
</form>
