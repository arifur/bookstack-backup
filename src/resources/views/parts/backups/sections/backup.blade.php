<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_description') }}</p>

<div class="grid half gap-xl mt-l mb-xl">
    <div>
        <p class="small text-muted mb-none">{{ trans('bookstack-backup::settings.backup_create') }}</p>
    </div>
    <div class="text-right">
        <form action="{{ route('backups.create') }}" method="POST">
            {!! csrf_field() !!}
            <button component="loading-button" type="submit" class="button">{{ trans('bookstack-backup::settings.backup_create') }}</button>
        </form>
    </div>
</div>
