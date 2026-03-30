@if(session('success'))
    <div class="success-msg mb-m">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="error-msg mb-m">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="error-msg mb-m">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
