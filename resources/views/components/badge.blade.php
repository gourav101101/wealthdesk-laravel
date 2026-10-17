@props(['value'])
@php($tone = in_array($value, ['Low','Completed','Approved','Active','Buy']) ? 'green' : (in_array($value, ['Medium','Pending','Hold']) ? 'amber' : (in_array($value, ['High','Declined','Sell']) ? 'red' : 'neutral')))
<span class="badge {{ $tone }}"><i></i>{{ $value }}</span>
