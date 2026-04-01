<h1 class="list-heading">{{ trans('bookstack-backup::settings.backup_heading') }}</h1>
<p class="small text-muted">{{ trans('bookstack-backup::settings.backup_description') }}</p>

@php
    $remoteEnabled = in_array(setting('backup-remote-enabled', true), [true, 'true', 1, '1'], true);
    $remoteUploadOnCreate = in_array(setting('backup-remote-upload-on-create', false), [true, 'true', 1, '1'], true);
    $ftpEnabled = in_array(setting('backup-ftp-enabled', false), [true, 'true', 1, '1'], true);
    $remoteProgressEnabled = $remoteEnabled && $remoteUploadOnCreate && $ftpEnabled;
@endphp

<div class="grid half gap-xl mt-l mb-xl">
    <div>
        <p class="small text-muted mb-none">{{ trans('bookstack-backup::settings.backup_create') }}</p>
    </div>
    <div class="text-right">
        <form action="{{ route('backups.create') }}" method="POST" data-backup-create-form data-remote-progress-enabled="{{ $remoteProgressEnabled ? 'true' : 'false' }}" data-progress-url-template="{{ route('backups.create-progress', ['token' => '__TOKEN__']) }}" data-redirect-url="{{ url('/settings/backups') }}">
            {!! csrf_field() !!}
            <div class="backup-create-actions">
                <div class="backup-remote-progress hidden" data-backup-progress aria-live="polite" aria-hidden="true">
                    <svg class="backup-remote-progress-ring" viewBox="0 0 36 36" role="img" aria-label="{{ trans('bookstack-backup::settings.backup_remote_progress') }}">
                        <circle class="backup-remote-progress-track" cx="18" cy="18" r="15.9155"></circle>
                        <circle class="backup-remote-progress-value" cx="18" cy="18" r="15.9155" stroke-dasharray="0 100"></circle>
                        <text x="18" y="20.5" text-anchor="middle" class="backup-remote-progress-text" data-backup-progress-number>0%</text>
                    </svg>
                </div>
                <button type="submit" class="button" data-backup-create-button>{{ trans('bookstack-backup::settings.backup_create') }}</button>
            </div>
            <p class="small text-muted mt-s hidden" data-backup-progress-label>{{ trans('bookstack-backup::settings.backup_remote_progress_waiting') }}</p>
        </form>
    </div>
</div>

@once
    @push('head')
        <style @if(!empty($cspNonce ?? null)) nonce="{{ $cspNonce }}" @endif>
            .backup-create-actions {
                display: inline-flex;
                align-items: center;
                justify-content: flex-end;
                gap: 12px;
            }

            .backup-remote-progress {
                width: 30px;
                height: 30px;
                flex: 0 0 auto;
            }

            .backup-remote-progress.hidden,
            [data-backup-progress-label].hidden {
                display: none;
            }

            .backup-remote-progress-ring {
                width: 30px;
                height: 30px;
                display: block;
            }

            .backup-remote-progress-track,
            .backup-remote-progress-value {
                fill: none;
                stroke-width: 3;
            }

            .backup-remote-progress-track {
                stroke: rgba(0, 0, 0, 0.12);
            }

            .backup-remote-progress-value {
                stroke: #0b7a75;
                transform: rotate(-90deg);
                transform-origin: 18px 18px;
                transition: stroke-dasharray 0.2s ease;
            }

            .backup-remote-progress-text {
                font-size: 7px;
                font-weight: 600;
                fill: currentColor;
            }
        </style>
    @endpush

    @push('body-end')
        <script @if(!empty($cspNonce ?? null)) nonce="{{ $cspNonce }}" @endif>
            (() => {
                const form = document.querySelector('[data-backup-create-form]');
                if (!form) {
                    return;
                }

                if (form.dataset.remoteProgressEnabled !== 'true') {
                    return;
                }

                const button = form.querySelector('[data-backup-create-button]');
                const progressWrap = form.querySelector('[data-backup-progress]');
                const progressCircle = form.querySelector('.backup-remote-progress-value');
                const progressNumber = form.querySelector('[data-backup-progress-number]');
                const progressLabel = form.querySelector('[data-backup-progress-label]');
                const progressUrlTemplate = form.dataset.progressUrlTemplate;
                const redirectUrl = form.dataset.redirectUrl;
                let pollTimer = null;
                let isCompleted = false;

                const setProgress = (percent, message) => {
                    const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
                    progressCircle.setAttribute('stroke-dasharray', `${safePercent} 100`);
                    progressNumber.textContent = `${Math.round(safePercent)}%`;
                    progressLabel.textContent = message;
                };

                const showProgress = () => {
                    progressWrap.classList.remove('hidden');
                    progressWrap.setAttribute('aria-hidden', 'false');
                    progressLabel.classList.remove('hidden');
                };

                const hideProgress = () => {
                    progressWrap.classList.add('hidden');
                    progressWrap.setAttribute('aria-hidden', 'true');
                    progressLabel.classList.add('hidden');
                };

                const progressUrlForToken = (token) => progressUrlTemplate.replace('__TOKEN__', encodeURIComponent(token));

                const generateToken = () => {
                    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                        return window.crypto.randomUUID();
                    }

                    return `backup-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                };

                const stopPolling = () => {
                    if (pollTimer !== null) {
                        window.clearInterval(pollTimer);
                        pollTimer = null;
                    }
                };

                const handleCompletion = (success, message) => {
                    if (isCompleted) {
                        return;
                    }

                    isCompleted = true;
                    stopPolling();
                    setProgress(success ? 100 : 0, message);

                    if (success) {
                        window.setTimeout(() => {
                            window.location.assign(redirectUrl);
                        }, 250);

                        return;
                    }

                    button.disabled = false;
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (button.disabled) {
                        return;
                    }

                    const token = generateToken();
                    const formData = new FormData(form);
                    formData.append('progress_token', token);

                    button.disabled = true;
                    showProgress();
                    setProgress(0, '{{ trans('bookstack-backup::settings.backup_remote_progress_starting') }}');

                    pollTimer = window.setInterval(async () => {
                        try {
                            const response = await fetch(progressUrlForToken(token), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                return;
                            }

                            const data = await response.json();
                            setProgress(data.percent ?? 0, data.message ?? '{{ trans('bookstack-backup::settings.backup_remote_progress_waiting') }}');

                            if (data.complete) {
                                handleCompletion(Boolean(data.success), data.message ?? '{{ trans('bookstack-backup::settings.backup_failed') }}');
                            }
                        } catch (error) {
                            stopPolling();
                        }
                    }, 500);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        const data = await response.json();
                        if (isCompleted) {
                            return;
                        }

                        if (!response.ok || !data.success) {
                            handleCompletion(false, data.message ?? '{{ trans('bookstack-backup::settings.backup_failed') }}');
                            return;
                        }

                        handleCompletion(true, data.message ?? '{{ trans('bookstack-backup::settings.backup_created') }}');
                    } catch (error) {
                        if (!isCompleted) {
                            handleCompletion(false, '{{ trans('bookstack-backup::settings.backup_failed') }}');
                        }
                    }
                });

                hideProgress();
            })();
        </script>
    @endpush
@endonce
