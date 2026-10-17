@extends('layouts.app')
@section('title', $client ? 'Edit client' : 'Add client')
@section('heading', $client ? 'Edit client' : 'A new client. A new possibility.')
@section('description', $client ? 'Client ID · '.$client->code : 'Add their details to start building the relationship.')
@section('actions')<a class="btn secondary" href="{{ route('clients.index') }}">Back to clients</a>@endsection
@section('content')
@php($details=$client?->details ?? [])
<section class="card edit-card"><form action="{{ $client ? route('clients.update',$client) : route('clients.store') }}" method="post" enctype="multipart/form-data">@csrf @if($client)@method('put')@endif<div class="section-tabs form-tabs" role="tablist"><button type="button" class="active" data-tab="personal">Personal details</button><button type="button" data-tab="bank">Bank &amp; nominee</button><button type="button" data-tab="documents">Documents</button></div><div class="modal-body"><div data-tab-panel="personal" class="form-grid">
<x-field name="first_name" label="First name" :value="$details['first_name'] ?? ''" :required="true" maxlength="60"/><x-field name="middle_name" label="Middle name" :value="$details['middle_name'] ?? ''" maxlength="60"/><x-field name="surname" label="Surname" :value="$details['surname'] ?? ''" maxlength="60"/>
<x-field name="code_display" label="Client ID" :value="$client?->code ?? 'Generated automatically'" disabled/>
<x-field name="email" label="Email address" type="email" :value="$client?->user->email" :required="true" maxlength="160"/><x-field name="phone" label="Phone number" type="tel" :value="$client?->phone" :required="true" maxlength="25"/>
<x-field name="pan" label="PAN number" :value="$client?->pan" placeholder="ABCDE1234F" maxlength="10"/><x-field name="investment" label="Opening investment (₹)" type="number" :value="$client ? $client->investment/100 : ''" :required="true" min="0" step="0.01" hint="Opening amount, before recorded deposits."/>
<x-field name="dob" label="Date of birth" type="date" :value="$details['dob'] ?? ''"/><x-field name="registration" label="Registration number" :value="$details['registration'] ?? ''" maxlength="100"/>
<x-field name="risk" label="Profile rate" :value="$client?->risk ?? 'Medium'" :options="\App\Services\Ledger::RISKS"/>
<x-field name="password" :label="$client ? 'Reset password (optional)' : 'Initial password'" type="password" :required="!$client" autocomplete="new-password" hint="10+ characters, including a letter and a number." maxlength="128"/>
<x-field name="address" label="Address" type="textarea" :value="$details['address'] ?? ''" :span="true" maxlength="500"/>
<x-field name="aadhaar" label="Aadhaar number" :value="$details['aadhaar'] ?? ''" maxlength="12" inputmode="numeric"/>
<x-field name="provide" label="Additional information (Provide)" :value="$details['provide'] ?? ''" hint="Optional information requested by the advisor." maxlength="200"/>
</div><div data-tab-panel="bank" class="form-grid" hidden><h3 class="form-section-title">Bank details</h3>
@foreach(['bank_name'=>'Bank name','account_number'=>'Account number','account_holder'=>'Account holder name','ifsc'=>'IFSC code'] as $key=>$label)<x-field :name="$key" :label="$label" :value="$details[$key] ?? ''" maxlength="100"/>@endforeach
<x-field name="account_type" label="Account type" :value="$details['account_type'] ?? ''" :options="[''=>'Select account type','Savings'=>'Savings','Current'=>'Current','Other'=>'Other']"/>
<h3 class="form-section-title">Nominee details</h3><x-field name="nominee" label="Relationship" :value="$details['nominee'] ?? ''" :options="[''=>'Select relationship','Spouse'=>'Spouse','Parent'=>'Parent','Child'=>'Child','Sibling'=>'Sibling','Other'=>'Other','None'=>'None']"/>
<x-field name="nominee_name" label="Nominee name" :value="$details['nominee_name'] ?? ''" maxlength="100"/><x-field name="nominee_pan" label="Nominee PAN" :value="$details['nominee_pan'] ?? ''" maxlength="10"/><x-field name="nominee_aadhaar" label="Nominee Aadhaar number" :value="$details['nominee_aadhaar'] ?? ''" maxlength="12"/>
</div><div data-tab-panel="documents" hidden><div class="upload-list">@foreach(['photo'=>'Profile picture','signature'=>'Signature','aadhaar_document'=>'Aadhaar document'] as $key=>$label)<label class="file-drop"><span class="file-drop-icon"><x-icon name="upload" :size="22"/></span><div><strong>{{ $label }}</strong><small data-file-name>Choose PNG, JPG, WebP{{ $key==='aadhaar_document' ? ' or PDF' : '' }} · Up to 5 MB</small></div><span class="btn secondary">Choose file</span><input type="file" name="{{ $key }}" accept="image/png,image/jpeg,image/webp{{ $key==='aadhaar_document' ? ',application/pdf' : '' }}"></label>@endforeach</div>@if($client)<div class="info-box">Existing documents remain available on the client profile until replaced.</div>@endif</div>
<div class="modal-actions"><span class="required-note">* Required fields</span><a class="btn secondary" href="{{ route('clients.index') }}">Cancel</a><button class="btn primary" type="submit"><x-icon name="check" :size="16"/>Save client</button></div></div></form></section>
@endsection
