{{-- Ứng dụng cài được của quán (PWA): icon, tên, màu khi thêm vào màn hình chính. --}}
@php($appVersion = \App\Http\Controllers\AppController::iconVersion())
<link rel="manifest" href="{{ route('tenant.manifest', ['v' => $appVersion]) }}">
<link rel="apple-touch-icon" href="{{ route('tenant.app-icon', ['size' => 180, 'v' => $appVersion]) }}">
<meta name="theme-color" content="{{ tenant()->theme['primary'] ?? \App\Tenancy\RestaurantTheme::PRIMARY }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ tenant()->name }}">
<meta name="app-scope" content="/{{ tenant()->slug }}/" data-sw="{{ asset('sw.js') }}">
