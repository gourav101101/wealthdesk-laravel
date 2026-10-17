@props(['title', 'icon'=>'layers'])
<div class="empty"><span><x-icon :name="$icon" :size="26"/></span><h3>{{ $title }}</h3><p>{{ $slot }}</p></div>
