<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.pwa-head')
    <title>@yield('title', 'PSU Intern-Link')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/internlink.css') }}?v={{ filemtime(public_path('css/internlink.css')) }}" rel="stylesheet">
    @stack('styles')
    <style>.text-uppercase::placeholder { text-transform: none; }</style>
    <link rel="stylesheet" href="{{ asset('vendor/tabler-icons/tabler-icons.min.css') }}">
</head>
<body class="il-shell-body il-staff">
    <div class="il-shell">
        @include('partials.mobile-nav')
        @include('partials.admin-sidebar')

        <main class="il-main">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.audit-tracker')
    @stack('scripts')
</body>
</html>
