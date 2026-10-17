@extends('layouts.app')
@section('title', $position ? 'Edit position' : 'Add position')
@section('heading', $position ? 'Edit position' : 'Add a position')
@section('description','Record an investment entry for your client.')
@section('actions')<a class="btn secondary" href="{{ route('positions.index') }}">Back to positions</a>@endsection
@section('content')
@php($options=$clients->where('archived',false)->mapWithKeys(fn($c)=>[$c->id=>$c->name.' · '.$c->code])->all())
<section class="card edit-card"><form method="post" action="{{ $position ? route('positions.update',$position) : route('positions.store') }}" data-position-form>@csrf @if($position)@method('put')@endif<div class="modal-body"><div class="form-grid"><x-field name="client_id" label="Client" :value="$position?->client_id" :options="$options" :required="true"/><x-field name="date" label="Date & time (IST)" type="datetime-local" :value="($position?->date ?? now())->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')" :required="true"/>
<x-field name="category" label="Market category" :value="$position?->category ?? request('category','Equity')" :options="\App\Services\Ledger::CATEGORIES"/><x-field name="section" label="Section" :value="$position?->section ?? request('section','Current positions')" :options="\App\Services\Ledger::SECTIONS"/>
<x-field name="stock" label="Stock / instrument name" :value="$position?->stock" :required="true" maxlength="100" placeholder="e.g. RELIANCE"/><x-field name="side" label="Side" :value="$position?->side ?? 'Buy'" :options="['Buy','Sell']"/>
<x-field name="quantity" label="Quantity" type="number" :value="$position?->quantity" :required="true" min="1" max="100000000" step="1"/>
@foreach(['entry_price'=>'Entry price (₹)','ltp'=>'LTP (₹)','exit_price'=>'Exit price (₹)','pnl'=>'Profit / loss (₹)'] as $key=>$label)<x-field :name="$key" :label="$label" type="number" :value="$position && $position->$key!==null ? $position->$key/100 : ''" :required="$key!=='exit_price'" step="0.01" :min="$key==='pnl' ? '-9999999999' : '0'"/>@endforeach
<x-field name="state" label="Position status" :value="$position?->state ?? 'Active'" :options="['Active','Hold','Closed']"/></div><div class="info-box">Only Last trades affect realized profit/loss and balance. Enter final P&amp;L after fees. Current positions, orders, and total value records do not change available funds.<button class="text-link" type="button" data-calculate-pnl>Calculate P&amp;L before fees <x-icon name="arrow" :size="14"/></button></div><div class="modal-actions"><a class="btn secondary" href="{{ route('positions.index') }}">Cancel</a><button type="submit" class="btn primary" @disabled(!$options)><x-icon name="check" :size="16"/>Save position</button></div></div></form></section>
@endsection
