<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitles[$activeMenu][0] }} · SIMAKOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        @include('components.sidebar')
        <div class="content-shell">
            @include('components.topbar')
            <main class="page-content">
                <div class="page-container">
                    @if (session('status'))
                        <div class="flash-message">{{ session('status') }}</div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
