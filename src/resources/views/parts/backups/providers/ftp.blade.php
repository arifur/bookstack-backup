<hr>
<h2 class="list-heading">{{ trans('bookstack-backup::settings.ftp_heading') }}</h2>
<div class="grid half gap-xl">
    <div>
        <label class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_enabled') }}</label>
    </div>
    <div>
        @include('form.toggle-switch', ['name' => 'setting-backup-ftp-enabled', 'value' => setting('backup-ftp-enabled', false), 'label' => trans('bookstack-backup::settings.ftp_enabled')])
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-ftp-host" class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_host') }}</label>
    </div>
    <div>
        <input type="text" id="setting-backup-ftp-host" name="setting-backup-ftp-host" value="{{ setting('backup-ftp-host', '') }}">
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-ftp-port" class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_port') }}</label>
    </div>
    <div>
        <input type="number" id="setting-backup-ftp-port" name="setting-backup-ftp-port" value="{{ setting('backup-ftp-port', '21') }}">
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-ftp-username" class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_username') }}</label>
    </div>
    <div>
        <input type="text" id="setting-backup-ftp-username" name="setting-backup-ftp-username" value="{{ setting('backup-ftp-username', '') }}">
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-ftp-password" class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_password') }}</label>
    </div>
    <div>
        <input type="password" id="setting-backup-ftp-password" name="setting-backup-ftp-password" value="{{ setting('backup-ftp-password', '') }}">
    </div>
</div>
<div class="grid half gap-xl items-center">
    <div>
        <label for="setting-backup-ftp-path" class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_path') }}</label>
    </div>
    <div>
        <input type="text" id="setting-backup-ftp-path" name="setting-backup-ftp-path" value="{{ setting('backup-ftp-path', '/') }}">
    </div>
</div>
<div class="grid half gap-xl">
    <div>
        <label class="setting-list-label">{{ trans('bookstack-backup::settings.ftp_passive') }}</label>
    </div>
    <div>
        @include('form.toggle-switch', ['name' => 'setting-backup-ftp-passive', 'value' => setting('backup-ftp-passive', true), 'label' => trans('bookstack-backup::settings.ftp_passive')])
    </div>
</div>
