<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_schedule_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_schedule_description') }}</p>

<form action="{{ route('backups.schedule.update') }}" method="POST">
    {!! csrf_field() !!}
    <div class="setting-list">
        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_enabled') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_enabled_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-schedule-enabled', 'value' => setting('backup-schedule-enabled', false), 'label' => trans('bookstack-backup::settings.backup_schedule_enabled')])
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-frequency" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_frequency') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_frequency_desc') }}</p>
            </div>
            <div>
                <select id="setting-backup-schedule-frequency" name="setting-backup-schedule-frequency">
                    <option value="daily" @if(setting('backup-schedule-frequency', 'daily') === 'daily') selected @endif>{{ trans('bookstack-backup::settings.frequency_daily') }}</option>
                    <option value="weekly" @if(setting('backup-schedule-frequency', 'daily') === 'weekly') selected @endif>{{ trans('bookstack-backup::settings.frequency_weekly') }}</option>
                    <option value="monthly" @if(setting('backup-schedule-frequency', 'daily') === 'monthly') selected @endif>{{ trans('bookstack-backup::settings.frequency_monthly') }}</option>
                </select>
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-time" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_time') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_time_desc') }}</p>
            </div>
            <div>
                <input type="time" id="setting-backup-schedule-time" name="setting-backup-schedule-time" value="{{ setting('backup-schedule-time', '02:00') }}">
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-day-of-week" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_day_of_week') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_day_of_week_desc') }}</p>
            </div>
            <div>
                <select id="setting-backup-schedule-day-of-week" name="setting-backup-schedule-day-of-week">
                    @foreach([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayValue => $dayLabel)
                        <option value="{{ $dayValue }}" @if((string) setting('backup-schedule-day-of-week', '0') === (string) $dayValue) selected @endif>{{ $dayLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-day-of-month" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_day_of_month') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_day_of_month_desc') }}</p>
            </div>
            <div>
                <input type="number" min="1" max="28" id="setting-backup-schedule-day-of-month" name="setting-backup-schedule-day-of-month" value="{{ setting('backup-schedule-day-of-month', '1') }}">
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-timezone" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_timezone') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_timezone_desc') }}</p>
            </div>
            <div>
                <input type="text" id="setting-backup-schedule-timezone" name="setting-backup-schedule-timezone" value="{{ setting('backup-schedule-timezone', config('app.timezone', 'UTC')) }}">
            </div>
        </div>

        <div class="grid half gap-xl">
            <div>
                <label class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_keep_local_copy') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_keep_local_copy_desc') }}</p>
            </div>
            <div>
                @include('form.toggle-switch', ['name' => 'setting-backup-schedule-keep-local-copy', 'value' => setting('backup-schedule-keep-local-copy', true), 'label' => trans('bookstack-backup::settings.backup_schedule_keep_local_copy')])
            </div>
        </div>

        <div class="grid half gap-xl items-center">
            <div>
                <label for="setting-backup-schedule-notify-email" class="setting-list-label">{{ trans('bookstack-backup::settings.backup_schedule_notify_email') }}</label>
                <p class="small">{{ trans('bookstack-backup::settings.backup_schedule_notify_email_desc') }}</p>
            </div>
            <div>
                <input type="email" id="setting-backup-schedule-notify-email" name="setting-backup-schedule-notify-email" value="{{ setting('backup-schedule-notify-email', '') }}">
            </div>
        </div>
    </div>

    <div class="form-group text-right">
        <button type="submit" class="button">{{ trans('bookstack-backup::settings.settings_save') }}</button>
    </div>
</form>
