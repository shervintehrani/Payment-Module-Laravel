{{--
    Minimal, dependency-free layout used by the package views.
    Publish and edit the views to match your application:

        php artisan vendor:publish --tag=payment-module-views
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Payments')</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; padding: 2rem 1rem; background: #f4f5f7; color: #1f2933;
               font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { max-width: 640px; margin: 0 auto; padding: 2rem; background: #fff;
                border-radius: 12px; box-shadow: 0 1px 4px rgba(0, 0, 0, .08); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        label { display: block; margin: 1rem 0 .25rem; font-weight: 600; }
        input, select { width: 100%; padding: .6rem .75rem; border: 1px solid #cbd2d9; border-radius: 8px; font: inherit; }
        button, .button { display: inline-block; margin-top: 1.25rem; padding: .65rem 1.25rem; border: 0; border-radius: 8px;
                          background: #2563eb; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .alert { padding: .75rem 1rem; margin-bottom: 1rem; border-radius: 8px; background: #fde8e8; color: #9b1c1c; }
        .alert p { margin: 0; }
        .badge { display: inline-block; padding: .15rem .6rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-failed  { background: #fde8e8; color: #9b1c1c; }
        table { width: 100%; border-collapse: collapse; font-size: .95rem; }
        th, td { padding: .5rem .4rem; border-bottom: 1px solid #e4e7eb; text-align: left; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: .4rem 1.25rem; }
        dt { color: #616e7c; } dd { margin: 0; }
    </style>
</head>
<body>
    <main class="card">
        @yield('content')
    </main>
</body>
</html>
