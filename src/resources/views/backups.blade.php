@extends('bookstack-backup::layout')

@section('card')
    @include('bookstack-backup::parts.backups.alerts')

    @if($section === 'backup')
        @include('bookstack-backup::parts.backups.sections.backup')
    @elseif($section === 'schedule')
        @include('bookstack-backup::parts.backups.sections.schedule')
    @elseif($section === 'backup-settings')
        @include('bookstack-backup::parts.backups.sections.backup-settings')
    @elseif($section === 'remote')
        @include('bookstack-backup::parts.backups.sections.remote')
    @endif
@endsection

@section('after-card')
    @if($section === 'backup')
        @include('bookstack-backup::parts.backups.history')
    @endif
@endsection
