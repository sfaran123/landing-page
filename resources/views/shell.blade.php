<!doctype html>
<html lang="he" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'לוח בקרה') · {{ config('app.name') }}</title>
    <style>html,body{margin:0;background:#f3f5fa}@media (prefers-color-scheme:dark){html,body{background:#0b1020}}</style>
</head>
<body>
@yield('content')
</body>
</html>
