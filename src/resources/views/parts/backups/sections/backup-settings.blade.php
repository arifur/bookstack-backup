<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_settings_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_settings_description') }}</p>

<form action="{{ route('backups.backup-settings.update') }}" method="POST">
    {!! csrf_field() !!}
    <div class="setting-list">
        <div class="grid half gap-xl">
            <div>
                <label for="setting-backup-filename-prefix" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_filename_prefix') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_filename_prefix_desc') }}</p>
            </div>
            <div>
                <input type="text" id="setting-backup-filename-prefix" name="setting-backup-filename-prefix" value="{{ setting('backup-filename-prefix', 'bookstack_backup') }}">
            </div>
        </div>

        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_include_database') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_include_database_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-include-database', 'value' => setting('backup-include-database', true), 'label' => trans('bookstack-backup::settings.backup_include_database')])
            </div>
        </div>

        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_include_files') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_include_files_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-include-files', 'value' => setting('backup-include-files', true), 'label' => trans('bookstack-backup::settings.backup_include_files')])
            </div>
        </div>

        {{-- <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_remote_upload_on_create') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_remote_upload_on_create_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-remote-upload-on-create', 'value' => setting('backup-remote-upload-on-create', false), 'label' => trans('bookstack-backup::settings.backup_remote_upload_on_create')])
            </div>
        </div> --}}

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-max-backups" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_max_backups') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_max_backups_desc') }}</p>
            </div>
            <div>
                <input type="number" min="1" max="1000" id="setting-backup-max-backups" name="setting-backup-max-backups" value="{{ setting('backup-max-backups', config('backups.max_backups', 10)) }}">
            </div>
        </div>
    </div>

    <div class="form-group text-right">
        <button type="submit" class="button">{{ trans('bookstack-backup::settings.settings_save') }}</button>
    </div>
</form>
