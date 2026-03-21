@props(['symbol', 'size' => 5])
<img
    src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/32/color/{{ strtolower($symbol) }}.png"
    alt="{{ $symbol }}"
    class="w-{{ $size }} h-{{ $size }} rounded-full shrink-0"
    onerror="this.replaceWith(document.createTextNode(''))"
>
