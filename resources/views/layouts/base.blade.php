<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- yieldContent đã escape sẵn, tên quán thì escape tại đây. --}}
    @php($pageTitle = $__env->yieldContent('title', tenant()?->name ?? __('ui.app')))
    <title>{!! tenant() && $pageTitle !== e(tenant()->name) ? e(tenant()->name).' - '.$pageTitle : $pageTitle !!}</title>
    {{-- Menu trái đã thu gọn (máy tính) thì thu ngay từ đầu, không bị giật khi tải trang. --}}
    <script>document.documentElement.classList.add('js');try{if(localStorage.getItem('sidebar')==='collapsed'){document.documentElement.classList.add('sidebar-collapsed')}}catch(e){}</script>
    <link rel="icon" href="{{ tenant() ? (tenant()->logoUrl() ?? asset('images/restaurant-icon.svg')) : asset('images/liresto-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ versioned_asset('css/app.css') }}">
    <script src="{{ versioned_asset('js/app.js') }}" defer></script>
    @stack('head')
    @if ($themeCss = tenant()?->themeCss())
        <style>:root { {{ $themeCss }} }</style>
    @endif
</head>
<body class="@yield('body_class')">
@yield('body')
</body>
</html>
