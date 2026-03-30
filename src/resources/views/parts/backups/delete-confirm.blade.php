@extends('bookstack-backup::layout')

@section('card')
    <h1 class="list-heading">{{ trans('bookstack-backup::settings.history_delete') }}</h1>
    <p class="small text-muted">{{ trans('bookstack-backup::settings.delete_confirm') }}</p>

    <div class="card content-wrap auto-height mt-m">
        <p class="mb-none"><strong>{{ $filename }}</strong></p>
    </div>

    <div class="form-group text-right mt-l">
        <a href="{{ url('/settings/backups') }}" class="button outline">{{ trans('common.cancel') }}</a>
        <form action="{{ route('backups.delete', $filename) }}" method="POST" style="display: inline-block; margin: 0;">
            {!! csrf_field() !!}
            @method('DELETE')
            <button type="submit" class="button">{{ trans('common.confirm') }}</button>
        </form>
    </div>
@endsection
