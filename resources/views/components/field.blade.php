@props(['name', 'label', 'value'=>null, 'type'=>'text', 'required'=>false, 'options'=>null, 'hint'=>null, 'span'=>false])
<label class="field {{ $span ? 'span-2' : '' }}"><span>{{ $label }}@if($required)<b> *</b>@endif</span>
@if($options !== null)
<select name="{{ $name }}" @required($required) {{ $attributes }}>@foreach($options as $key=>$option)@php($optionValue = array_is_list($options) ? $option : $key)<option value="{{ $optionValue }}" @selected((string)old($name, $value) === (string)$optionValue)>{{ $option }}</option>@endforeach</select>
@elseif($type === 'textarea')<textarea name="{{ $name }}" rows="3" @required($required) {{ $attributes }}>{{ old($name, $value) }}</textarea>
@else<input name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' || $type === 'file' ? '' : old($name, $value) }}" @required($required) {{ $attributes }}>@endif
@if($hint)<small>{{ $hint }}</small>@endif
@error($name)<small class="negative">{{ $message }}</small>@enderror</label>
