@extends('layouts.simple')

@section('body')
    <div class="container medium">

        @include('settings.parts.navbar', ['selected' => 'backups'])

        <div class="grid gap-xxl right-focus">
            <div>
                <h5>{{ trans('bookstack-backup::settings.categories') }}</h5>
                <nav class="active-link-list in-sidebar">
                    @foreach($sections as $item)
                        <a href="{{ $item['url'] }}" class="{{ $section === $item['key'] ? 'active' : '' }}">{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <h5 class="mt-xl">{{ trans('settings.system_version') }}</h5>
                <div class="py-xs">
                    <a target="_blank" rel="noopener noreferrer" href="https://github.com/BookStackApp/BookStack/releases">
                        BookStack @if(!str_starts_with($version, 'v')) version @endif {{ $version }}
                    </a>
                    <br>
                    <a target="_blank" href="{{ url('/licenses') }}" class="text-muted">{{ trans('settings.license_details') }}</a>
                </div>
            </div>

            <div>
                <div class="card content-wrap auto-height">
                    @yield('card')
                </div>
                @yield('after-card')
            </div>
        </div>

    </div>
@stop