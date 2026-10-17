@extends('layouts.app')
@section('title','Activity log')
@section('heading','Activity log')
@section('description','A transparent record of changes in your workspace.')
@section('content')
<section class="card"><div class="card-heading"><div><h2>Workspace history</h2><p>Most recent 100 events · All times in India Standard Time</p></div><x-icon name="clock" class="muted"/></div><div class="table-wrap activity-table"><table><thead><tr><th>DATE &amp; TIME</th><th>ACTIVITY</th><th>ACTOR</th></tr></thead><tbody>@forelse($events as $event)<tr><td class="muted">{{ \App\Support\Format::date($event->created_at,true) }}</td><td><strong>{{ $event->description }}</strong></td><td class="muted">{{ $event->actor?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="3"><x-empty title="A fresh start" icon="clock">Your workspace activity will appear here.</x-empty></td></tr>@endforelse</tbody></table></div></section>
@endsection
