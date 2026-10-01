@extends(config('smart-dashboard.extends') ?: 'smart-dashboard::shell')
@section('title', 'לוח בקרה')
@section(config('smart-dashboard.section', 'content'))
    @include('smart-dashboard::page', ['page' => 'dashboard', 'boot' => $boot, 'urls' => $urls])
@endsection
