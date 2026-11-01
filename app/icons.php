<?php
// Inline SVG line icons (24×24 grid, stroke-based).

function icon(string $name, int $size = 20): string
{
    static $paths = [
        'cart'      => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h2.6l2.4 12.2a1.6 1.6 0 0 0 1.6 1.3h8.7a1.6 1.6 0 0 0 1.6-1.2L21 7H6"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'phone'     => '<path d="M5 3h3.5l1.8 4.5-2.3 1.4a11 11 0 0 0 7.1 7.1l1.4-2.3L21 15.5V19a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2Z"/>',
        'pin'       => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'bell'      => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15Z"/><path d="M10 21h4"/>',
        'crosshair' => '<circle cx="12" cy="12" r="7"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'      => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'minus'     => '<path d="M5 12h14"/>',
        'trash'     => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'edit'      => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'eye'       => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'receipt'   => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'utensils'  => '<path d="M7 2v20M4 2v6a3 3 0 0 0 6 0V2M17 22V2c-2.5 1.5-4 4-4 8h4"/>',
        'tag'       => '<path d="M3 12V3h9l9 9-9 9Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'inbox'     => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5 5h14l2 8v6H3v-6Z"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'star'      => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.6l6.2-.9Z"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 0 1-4 0v-.1a1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3.1 14H3a2 2 0 0 1 0-4h.1a1.7 1.7 0 0 0 1.2-2.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 2.9-1.2V3a2 2 0 0 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9H21a2 2 0 0 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'logout'    => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
        'truck'     => '<path d="M2 6h11v10H2ZM13 9h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'bag'       => '<path d="M5 8h14l-1 13H6Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'leaf'      => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19 13 11"/>',
        'flame'     => '<path d="M12 22a7 7 0 0 0 7-7c0-4-3-6-4-9-1 2-2 3-4 3 0-2-1-4-2-6-1 4-4 7-4 12a7 7 0 0 0 7 7Z"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        'link'      => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'store'     => '<path d="M4 9h16l-1.5-5h-13ZM5 9v11h14V9"/><path d="M10 20v-6h4v6"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
