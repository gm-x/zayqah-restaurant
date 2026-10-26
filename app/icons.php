<?php
/** Small, self-contained outline icons for the web. No icon font or Apple assets. */
function ui_icon(string $name, int $size=20): string {
    static $paths = [
         'burger'=>'<path d="M4 9a8 6 0 0 1 16 0ZM3 13h18M4 17h16v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/>',
        'pizza'=>'<path d="M3 20 9 3a21 21 0 0 1 12 12L3 20Zm4-12a17 17 0 0 1 9 9"/><circle cx="9" cy="14" r="1"/><circle cx="13" cy="11" r="1"/>',
        'leaf'=>'<path d="M20 3C9 2 2 8 5 15s15 8 15-12ZM4 21 15 10"/>',
        'chicken'=>'<path d="M9 14c-6-9 7-14 11-7s-3 13-7 10l-4 4-2-2-2-2 4-3Z"/>',
        'dessert'=>'<path d="m4 10 8-7 8 7v11H4V10Zm0 1h16M4 16h16"/><path d="M12 3V1"/>',
        'pasta'=>'<path d="M3 14h18a9 7 0 0 1-18 0ZM7 11V5m5 6V3m5 8V5M7 5h12M7 8h10"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'refresh'=>'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 7a7 7 0 0 1 12-1l2 3M4 15l2 3a7 7 0 0 0 12-1"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/>',
        'heart'=>'<path d="M12 20S3 15 3 8.5a4.5 4.5 0 0 1 9-1 4.5 4.5 0 0 1 9 1C21 15 12 20 12 20Z"/>',
        'bowl'=>'<path d="M3 12h18a9 9 0 0 1-18 0Zm4 9h10M8 3v5m4-6v6m4-5v5"/>',
        'store'=>'<path d="m3 9 2-6h14l2 6M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M5 12v9h14v-9M10 21v-6h4v6"/>',
        'chart'=>'<path d="M4 3v18h17M8 16v-5m5 5V6m5 10v-8"/>',
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'bag'=>'<rect x="4" y="7" width="16" height="14" rx="3"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/>',
        'user'=>'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
    ];
    $path=$paths[$name]??$paths['grid'];
    return '<svg class="ui-icon" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$path.'</svg>';
}

/** Category artwork is rendered as SVG so it does not depend on emoji fonts. */
function category_ui_icon(string $name, int $size=32): string {
    $name=mb_strtolower(trim($name));
    $icons=['burgers'=>'burger','burger'=>'burger','pizza'=>'pizza','healthy'=>'leaf','chicken'=>'chicken','desserts'=>'dessert','dessert'=>'dessert','pasta'=>'pasta','desi'=>'bowl'];
    return ui_icon($icons[$name]??'bowl',$size);
}
