@props(['symbol', 'size' => 5])
@php
    $lower   = strtolower($symbol);
    $letters = strlen($symbol) <= 3 ? strtoupper($symbol) : strtoupper(substr($symbol, 0, 2));
    $colors  = [
        'btc'   => ['#f7931a','#fff'],
        'eth'   => ['#627eea','#fff'],
        'sol'   => ['#9945ff','#fff'],
        'xrp'   => ['#00aae4','#fff'],
        'doge'  => ['#c2a633','#fff'],
        'shib'  => ['#e4362d','#fff'],
        'ape'   => ['#0054f9','#fff'],
        'ada'   => ['#0033ad','#fff'],
        'avax'  => ['#e84142','#fff'],
        'link'  => ['#2a5ada','#fff'],
        'dot'   => ['#e6007a','#fff'],
        'ltc'   => ['#bfbbbb','#333'],
        'uni'   => ['#ff007a','#fff'],
        'atom'  => ['#2e3148','#fff'],
        'fil'   => ['#0090ff','#fff'],
        'algo'  => ['#000','#fff'],
        'mana'  => ['#ff2d55','#fff'],
        'crv'   => ['#f5c542','#333'],
        'grt'   => ['#6f4cff','#fff'],
        'bat'   => ['#ff5000','#fff'],
        'chz'   => ['#cd0124','#fff'],
        'mina'  => ['#e39844','#fff'],
        'snx'   => ['#00d1ff','#333'],
        'xlm'   => ['#000','#fff'],
        'xtz'   => ['#2c7df7','#fff'],
        '1inch' => ['#1b314f','#fff'],
    ];
    [$bg, $fg] = $colors[$lower] ?? ['#ffffff1a', '#ffffffaa'];
    $px = ['4'=>'16px','5'=>'20px','6'=>'24px','7'=>'28px','8'=>'32px','9'=>'36px','10'=>'40px'];
    $fs = ['4'=>'7px','5'=>'8px','6'=>'9px','7'=>'10px','8'=>'11px','9'=>'12px','10'=>'13px'];
    $dim = $px[$size] ?? '20px';
    $font = $fs[$size] ?? '8px';
@endphp
<span
    data-asset-icon="{{ $lower }}"
    class="relative inline-flex shrink-0 w-{{ $size }} h-{{ $size }}"
    style="width:{{ $dim }};height:{{ $dim }}"
>
    <img
        src="https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/32/color/{{ $lower }}.png"
        alt="{{ $symbol }}"
        class="w-full h-full rounded-full"
        style="display:block;box-shadow:0 0 0 1.5px rgba(255,255,255,0.18)"
        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
    >
    <span
        class="absolute inset-0 rounded-full flex items-center justify-center font-bold"
        style="display:none;background:{{ $bg }};color:{{ $fg }};font-size:{{ $font }};line-height:1;box-shadow:0 0 0 1.5px rgba(255,255,255,0.18)"
    >{{ $letters }}</span>
</span>
