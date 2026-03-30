@if(userCan(\BookStack\Permissions\Permission::SettingsManage))
    <a href="{{ url('/settings/backups') }}" @if($selected == 'backups') class="active" @endif>@icon('archive'){{ trans('bookstack-backup::settings.backups') }}</a>
@endif
