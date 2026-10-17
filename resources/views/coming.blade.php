@extends('layouts.app')
@section('title',$feature)
@section('heading',$feature)
@section('description','We’re making room for what’s next.')
@section('content')
<section class="card coming-card"><div class="coming-art"><div></div><div></div><span><x-icon name="sparkles" :size="40"/></span></div><span class="coming-pill"><x-icon name="clock" :size="13"/>ON THE HORIZON</span><h2>A little more possibility.<br>Coming soon.</h2><p>{{ $feature }} is planned for a future release, as specified in the requirements.</p><a class="btn secondary" href="{{ route('dashboard') }}">Back to overview <x-icon name="arrow" :size="16"/></a></section>
@endsection
