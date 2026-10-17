@extends('layouts.app')
@section('title', $ownProfile ? 'My profile' : 'Client profile')
@section('heading', $ownProfile ? 'My profile' : $profile->name)
@section('description','Your details, all in one place.')
@section('actions')
@if(auth()->user()->isAdmin())<button class="btn secondary" type="button" data-reveal-client-password="{{ route('clients.password.reveal',$profile) }}" data-client-name="{{ $profile->name }}"><x-icon name="key" :size="16"/>View password</button>@endif
@if(auth()->user()->isAdmin() && !$profile->archived)<a class="btn primary" href="{{ route('clients.edit',$profile) }}"><x-icon name="edit" :size="16"/>Edit client</a>@endif
@endsection
@section('content')
@php
use App\Support\Format as F;
$details=$profile->details; $photo=$profile->documents->firstWhere('kind','photo');
$groups = ['Personal details'=>['Date of birth'=>'dob','Registration number'=>'registration','Address'=>'address','Aadhaar number'=>'aadhaar','Additional information'=>'provide'],'Bank details'=>['Bank name'=>'bank_name','Account number'=>'account_number','Account holder'=>'account_holder','IFSC code'=>'ifsc','Account type'=>'account_type'],'Nominee details'=>['Relationship'=>'nominee','Name'=>'nominee_name','PAN number'=>'nominee_pan','Aadhaar number'=>'nominee_aadhaar']];
@endphp
<div class="profile-layout"><section class="card profile-identity"><span class="avatar large color-{{ $profile->id%5 }}">@if($photo)<img src="{{ route('documents.show',$photo) }}" alt="Profile photo">@else{{ F::initials($profile->name) }}@endif</span><h2>{{ $profile->name }}</h2><span class="client-id">{{ $profile->code }}</span><x-badge :value="$profile->risk"/>@if($profile->sample)<span class="sample-tag">FICTIONAL SAMPLE CLIENT</span>@endif
@if($ownProfile)<form method="post" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="photo-form">@csrf<label class="btn secondary upload-button"><x-icon name="upload" :size="15"/>Change photo<input type="file" name="photo" accept="image/png,image/jpeg,image/webp" data-auto-upload></label><noscript><button class="btn secondary">Upload</button></noscript></form>@endif
<div class="profile-balance"><span>Available balance</span><strong>{{ F::money($profileBalance['available']) }}</strong></div>@if($ownProfile)<button class="btn secondary" type="button" data-open-dialog="password-dialog"><x-icon name="key" :size="15"/>Change password</button>@endif<small>Client since {{ F::date($profile->created_at) }}</small>@if($profile->user->password_changed_at)<small class="positive">Password changed {{ F::date($profile->user->password_changed_at,true) }}</small>@endif</section>
<div class="profile-details">@foreach($groups as $title=>$fields)<section class="card"><div class="card-heading"><h2>{{ $title }}</h2><x-icon :name="$loop->first ? 'user' : ($loop->index===1 ? 'bank' : 'users')" class="muted"/></div><dl class="detail-grid">@if($loop->first)<div><dt>Email</dt><dd>{{ $profile->user->email }}</dd></div><div><dt>Phone</dt><dd>{{ $profile->phone }}</dd></div><div><dt>PAN number</dt><dd>{{ $profile->pan ?: '—' }}</dd></div><div><dt>Opening investment</dt><dd>{{ F::money($profile->investment) }}</dd></div>@endif @foreach($fields as $label=>$key)<div><dt>{{ $label }}</dt><dd>{{ $details[$key] ?? '—' }}</dd></div>@endforeach</dl></section>@endforeach
<section class="card"><div class="card-heading"><h2>Documents</h2><x-icon name="file" class="muted"/></div><div class="document-list">@forelse($profile->documents->where('kind','!=','photo') as $document)<a href="{{ route('documents.show',$document) }}" target="_blank" rel="noreferrer"><x-icon name="file" :size="19"/><div><strong>{{ $document->kind==='aadhaar' ? 'Aadhaar document' : 'Signature' }}</strong><small>{{ $document->original_name }}</small></div><x-icon name="download" :size="16"/></a>@empty<p class="muted">No documents uploaded.</p>@endforelse</div></section></div></div>
@if($ownProfile)@include('partials.password-dialog')@endif
@if(auth()->user()->isAdmin())@include('partials.client-password-reveal')@endif
@endsection
