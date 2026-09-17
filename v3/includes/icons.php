<?php
declare(strict_types=1);

function v3_icon(string $name, string $label = ''): string
{
    $aliases = [
        'grid' => 'layout-dashboard',
        'card' => 'credit-card',
        'trainer' => 'dumbbell',
        'calendar' => 'calendar-days',
        'wallet' => 'wallet-cards',
        'staff' => 'user-round-cog',
        'scan' => 'scan-line',
    ];
    $allowed = [
        'calendar-days', 'check', 'chevron-down', 'circle-alert', 'credit-card',
        'dumbbell', 'eye', 'eye-off', 'history', 'layout-dashboard', 'log-out',
        'menu', 'monitor', 'moon', 'scan-line', 'sun', 'user-round-cog', 'users',
        'wallet-cards', 'x',
    ];
    $icon = $aliases[$name] ?? $name;
    if (!in_array($icon, $allowed, true)) {
        $icon = 'circle-alert';
    }
    $labelMarkup = $label === '' ? ' aria-hidden="true"' : ' role="img" aria-label="' . e($label) . '"';
    return '<svg class="icon"' . $labelMarkup . '><use href="#icon-' . e($icon) . '"></use></svg>';
}

function v3_icon_sprite(): string
{
    $path = V3_ROOT . '/assets/icons/lucide.svg';
    return is_file($path) ? (string) file_get_contents($path) : '';
}
