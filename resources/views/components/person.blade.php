@props(['client'])
@php($photo = $client->documents->firstWhere('kind', 'photo'))
<div class="person"><span class="avatar color-{{ $client->id % 5 }}">@if($photo)<img src="{{ route('documents.show', $photo) }}" alt="">@else{{ \App\Support\Format::initials($client->name) }}@endif</span><div><strong>{{ $client->name }}</strong><small>{{ $client->code }}</small></div></div>
