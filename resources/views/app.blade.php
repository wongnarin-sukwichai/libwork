<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- sub-path ที่ติดตั้ง เช่น /libwork (อ่านโดย resources/js/utils/url.js) --}}
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
