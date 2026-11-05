<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('ui.app'))</title>
    <link rel="icon" href="{{ tenant() ? (tenant()->logoUrl() ?? asset('images/restaurant-icon.svg')) : asset('images/liresto-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if ($themeCss = tenant()?->themeCss())
        <style>:root { {{ $themeCss }} }</style>
    @endif
</head>
<body class="@yield('body_class')">
@yield('body')
</body>
</html>
