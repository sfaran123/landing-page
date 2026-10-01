@extends(config('smart-dashboard.extends') ?: 'smart-dashboard::shell')
@section('title', 'התאמת לוח הבקרה')
@section(config('smart-dashboard.section', 'content'))
    @include('smart-dashboard::page', ['page' => 'editor', 'boot' => $boot, 'urls' => $urls])
@endsection
