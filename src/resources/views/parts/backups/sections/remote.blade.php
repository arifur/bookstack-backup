<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_remote_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_remote_description') }}</p>

<form action="{{ route('backups.remote.update') }}" method="POST">
    {!! csrf_field() !!}
    <div class="setting-list">
        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-remote-default-provider" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_remote_default_provider') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_remote_default_provider_desc') }}</p>
            </div>
            <div>
                <select id="setting-backup-remote-default-provider" name="setting-backup-remote-default-provider">
                    <option value="none" @if(setting('backup-remote-default-provider', 'none') === 'none') selected @endif>{{ trans('bookstack-backup::settings.provider_none') }}</option>
                    <option value="ftp" @if(setting('backup-remote-default-provider', 'none') === 'ftp') selected @endif>{{ trans('bookstack-backup::settings.provider_ftp') }}</option>
                </select>
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
