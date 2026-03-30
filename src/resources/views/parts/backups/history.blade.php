<div class="card content-wrap auto-height mt-l">
    <h2 class="list-heading">{{ trans('bookstack-backup::settings.history_heading') }}</h2>
    @if(count($backups) > 0)
        <div class="item-list">
            <div class="item-list-row flex-container-row items-center px-s bold hide-under-l">
                <div class="flex px-m py-xs">{{ trans('bookstack-backup::settings.history_created') }}</div>
                <div class="flex-2 px-m py-xs">{{ trans('bookstack-backup::settings.history_filename') }}</div>
                <div class="flex px-m py-xs">{{ trans('bookstack-backup::settings.history_size') }}</div>
                <div class="flex px-m py-xs text-m-right"></div>
            </div>

            @foreach($backups as $backup)
                @php
                    $createdDateOnly = explode(' ', $backup['created_date'])[0] ?? $backup['created_date'];
                    $displayFilename = preg_replace('/_(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}(\.zip)$/', '_$1$2', $backup['filename']);
                @endphp
                <div class="item-list-row flex-container-row items-center px-s wrap">
                    <div class="flex px-m py-xs" title="{{ $backup['created_date'] }}">{{ $createdDateOnly }}</div>
                    <div class="flex-2 px-m py-xs break-text" title="{{ $backup['filename'] }}">{{ $displayFilename }}</div>
                    <div class="flex px-m py-xs">{{ $backup['size'] }}</div>
                    <div class="flex-none min-width-s px-m py-xs text-m-right">
                        <a href="{{ route('backups.download', $backup['filename']) }}" class="button small">{{ trans('bookstack-backup::settings.history_download') }}</a>
                        <a href="{{ route('backups.delete.confirm', $backup['filename']) }}" class="button small outline text-neg">{{ trans('bookstack-backup::settings.history_delete') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="small text-muted mb-none">{{ trans('bookstack-backup::settings.previous_empty') }}</p>
    @endif
</div>
