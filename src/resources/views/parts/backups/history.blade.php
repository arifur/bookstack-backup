<div class="card content-wrap auto-height mt-l">
    <h2 class="list-heading">{{ trans('bookstack-backup::settings.history_heading') }}</h2>
    @if(count($backups) > 0)
        <div class="item-list">
            <div class="item-list-row flex-container-row items-center px-s bold hide-under-l">
                <div class="flex px-m py-xs">{{ trans('bookstack-backup::settings.history_created') }}</div>
                <div class="flex-2 px-m py-xs">{{ trans('bookstack-backup::settings.history_filename') }}</div>
                <div class="flex px-m py-xs">{{ trans('bookstack-backup::settings.history_size') }}</div>
                <div class="flex px-m py-xs"></div>
                <div class="flex px-m py-xs text-m-right"></div>
            </div>

            @foreach($backups as $backup)
                @php
                    $isDeleted = ($backup['status'] ?? '') === 'deleted';
                    $createdDateOnly = explode(' ', $backup['created_date'])[0] ?? $backup['created_date'];
                    $displayFilename = preg_replace('/_(\d{4}-\d{2}-\d{2})_\d{2}-\d{2}-\d{2}(\.zip)$/', '_$1$2', $backup['filename']);
                    $hashValueId = 'hash-value-' . hash('md5', $backup['filename']);
                    $hashButtonId = 'hash-copy-' . hash('md5', $backup['filename']);
                @endphp
                <div class="item-list-row flex-container-row items-center px-s wrap">
                    <div class="flex px-m py-xs" title="{{ $backup['created_date'] }}">{{ $createdDateOnly }}</div>
                    <div class="flex-2 px-m py-xs break-text" title="{{ $backup['filename'] }}">{{ $displayFilename }}</div>
                    <div class="flex px-m py-xs">{{ $backup['size'] }}</div>
                    <div class="flex px-m py-xs">
                        @if($isDeleted)
                            <span class="text-muted small">Deleted by {{ $backup['deleted_by_name'] ?? 'Unknown user' }}</span>
                        @elseif(isset($backup['sha256']))
                            <div component="dropdown" class="dropdown-container">
                                <button refs="dropdown@toggle" type="button" class="button small" aria-haspopup="true" aria-expanded="false">{{ trans('bookstack-backup::settings.history_show_hash') }}</button>
                                <div refs="dropdown@menu" class="dropdown-menu" role="menu" style="left: 50%; transform: translateX(-50%); width: min(90vw, 32rem);">
                                    <div class="px-m py-s">
                                        <div class="flex-container-row items-center justify-space-between mb-xs">
                                            <div class="text-small">SHA256</div>
                                            <a href="#" class="text-button icon px-xs" aria-label="{{ trans('bookstack-backup::settings.history_close_hash') }}" onclick="event.preventDefault(); this.closest('[component=dropdown]').querySelector('[refs=\'dropdown@toggle\']').click();">@icon('close')</a>
                                        </div>
                                        <p class="small text-muted mb-s">{{ trans('bookstack-backup::settings.history_hash_desc') }}</p>
                                        <code id="{{ $hashValueId }}" class="block p-xs bg-page-alt break-text">{{ $backup['sha256'] }}</code>
                                        <div class="pt-s flex-container-row items-center gap-xs">
                                            <span id="{{ $hashButtonId }}" class="button small" role="button" tabindex="0" onclick="(async () => { const hash = document.getElementById('{{ $hashValueId }}').textContent.trim(); const btn = document.getElementById('{{ $hashButtonId }}'); try { if (window.isSecureContext && navigator.clipboard) { await navigator.clipboard.writeText(hash); } else { const temp = document.createElement('textarea'); temp.style.position = 'absolute'; temp.style.left = '-1000px'; temp.style.top = '-1000px'; temp.value = hash; document.body.appendChild(temp); temp.select(); document.execCommand('copy'); document.body.removeChild(temp); } btn.textContent = '{{ trans('bookstack-backup::settings.history_hash_copied') }}'; setTimeout(() => btn.textContent = '{{ trans('bookstack-backup::settings.history_copy_hash') }}', 1400); } catch (e) { try { const temp = document.createElement('textarea'); temp.style.position = 'absolute'; temp.style.left = '-1000px'; temp.style.top = '-1000px'; temp.value = hash; document.body.appendChild(temp); temp.select(); document.execCommand('copy'); document.body.removeChild(temp); btn.textContent = '{{ trans('bookstack-backup::settings.history_hash_copied') }}'; setTimeout(() => btn.textContent = '{{ trans('bookstack-backup::settings.history_copy_hash') }}', 1400); } catch (fallbackError) { btn.textContent = '{{ trans('bookstack-backup::settings.history_copy_failed') }}'; setTimeout(() => btn.textContent = '{{ trans('bookstack-backup::settings.history_copy_hash') }}', 1400); } } })();" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">{{ trans('bookstack-backup::settings.history_copy_hash') }}</span>
                                            <a href="#" class="button outline small" onclick="event.preventDefault(); this.closest('[component=dropdown]').querySelector('[refs=\'dropdown@toggle\']').click();">{{ trans('bookstack-backup::settings.history_close_hash') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </div>
                    <div class="flex-none min-width-s px-m py-xs text-m-right">
                        @if(!$isDeleted)
                            <a href="{{ route('backups.download', $backup['filename']) }}" class="button small">{{ trans('bookstack-backup::settings.history_download') }}</a>
                            <a href="{{ route('backups.delete.confirm', $backup['filename']) }}" class="button small outline text-neg">{{ trans('bookstack-backup::settings.history_delete') }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="small text-muted mb-none">{{ trans('bookstack-backup::settings.previous_empty') }}</p>
    @endif
</div>
