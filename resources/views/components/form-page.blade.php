@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <section class="panel settings-form" style="max-width:820px">
        @yield('form-content')
    </section>
@endsection
