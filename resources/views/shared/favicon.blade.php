@if(filled($settings['favicon_path'] ?? null))
    <link rel="icon" type="image/png" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['favicon_path']) }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings['favicon_path']) }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/images/favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/favicon-180.png') }}">
@endif
