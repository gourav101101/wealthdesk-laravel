@props(['name' => 'chart', 'size' => 18])
@php
$paths = [
 'overview'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M22 21v-2a4 4 0 0 0-3-3.87 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M16 3.13a4 4 0 0 1 0 7.75',
 'chart'=>'M3 3v18h18 M7 14l4-5 4 3 6-8',
 'transactions'=>'M3 7h17 M16 3l4 4-4 4 M21 17H4 M8 13l-4 4 4 4',
 'wallet'=>'M20 8V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6 M20 12h-6v5h6',
 'bank'=>'M3 10h18 M3 21h18 M5 10v11 M10 10v11 M15 10v11 M20 10v11 M12 2 2 7h20z',
 'qr'=>'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h2v2h-2z M21 15v6h-6 M12 3v2 M12 9v3h9 M3 12h5',
 'user'=>'M20 21v-2a7 7 0 0 0-14 0v2 M13 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
 'settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1z',
 'shield'=>'M12 3 3 7v5c0 5 9 10 9 10s9-5 9-10V7z M8 12l3 3 5-6',
 'file'=>'M14 2H4v20h16V8z M14 2v6h6 M8 12h8 M8 16h8',
 'layers'=>'m12 3 10 6-10 6L2 9z M2 13l10 6 10-6 M2 17l10 6 10-6',
 'sparkles'=>'m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z',
 'plus'=>'M12 5v14 M5 12h14', 'arrow'=>'M5 12h14 M13 6l6 6-6 6',
 'up-right'=>'M7 17 17 7 M7 7h10v10', 'down-left'=>'M17 7 7 17 M7 7v10h10',
 'chevron'=>'m9 5 7 7-7 7', 'down'=>'m6 9 6 6 6-6',
 'search'=>'M21 21l-6-6 M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14',
 'logout'=>'M9 3H3v18h6 M9 12h12 M16 7l5 5-5 5', 'check'=>'m5 12 4 4L19 6',
 'close'=>'m6 6 12 12 M6 18 18 6', 'edit'=>'m15 4 5 5 M3 21l2-7L17 2l5 5L10 19z',
 'trash'=>'M3 6h18 M9 6V3h6v3 M5 6l1 15h12l1-15 M10 10v7 M14 10v7',
 'eye'=>'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12 M15 12a3 3 0 1 0-6 0 3 3 0 0 0 6 0',
 'clock'=>'M12 8v5l4 2 M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0',
 'download'=>'M12 3v12 M7 10l5 5 5-5 M3 16v5h18v-5',
 'upload'=>'M12 16V4 M7 9l5-5 5 5 M3 17v4h18v-4',
 'pause'=>'M8 4v16 M16 4v16', 'play'=>'m6 3 15 9-15 9z',
 'key'=>'M14 10l8-8 M17 5l2 2 M11 13a5 5 0 1 0-10 0 5 5 0 0 0 10 0',
 'menu'=>'M3 6h18 M3 12h18 M3 18h18', 'help'=>'M9 8a3 3 0 1 1 4 3v3 M12 17h.01 M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0',
 'bell'=>'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
];
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}><path d="{{ $paths[$name] ?? $paths['chart'] }}"/></svg>
