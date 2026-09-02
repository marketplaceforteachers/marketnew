<?php

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/** Truncates a string to $length bytes with an ellipsis, without depending on ext-mbstring. */
function truncate(string $text, int $length = 120): string
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return rtrim(substr($text, 0, $length)) . '…';
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug . '-' . base_convert((string) time(), 10, 36);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function set_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function param(string $key, string $default = ''): string
{
    return trim((string) ($_GET[$key] ?? $default));
}

function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Splits a site name into the two-tone wordmark halves, mirroring the React Logo.tsx logic
 * exactly (split before each capital letter, first ceil(n/2) words vs. the rest).
 */
function split_site_name(string $siteName): array
{
    $withoutTld = preg_replace('/\.com$/i', '', $siteName);
    preg_match_all('/[A-Z][a-z]*/', $withoutTld, $matches);
    $words = $matches[0];
    if (count($words) <= 1) {
        return ['first' => $withoutTld, 'rest' => ''];
    }
    $mid = max(1, (int) ceil(count($words) / 2));
    return [
        'first' => implode('', array_slice($words, 0, $mid)),
        'rest' => implode('', array_slice($words, $mid)),
    ];
}

/**
 * The brand mark: open book + graduation cap + tassel + star, exactly as designed in
 * assets/img/logo.svg / favicon.svg (the source-of-truth brand assets). Reproduced inline
 * here (rather than an <img>) so it recolors correctly and stays crisp at any size.
 */
function logo_mark_svg(): string
{
    return '<svg width="24" height="21" viewBox="3 3 29 26" fill="none">'
        . '<defs><linearGradient id="mft-cap-grad" x1="0%" y1="0%" x2="100%" y2="100%">'
        . '<stop offset="0%" stop-color="#38bdf8"/><stop offset="100%" stop-color="#2563eb"/></linearGradient></defs>'
        . '<path d="M5 26.5C8.5 24.5 13 24.8 18 27.5C23 24.8 27.5 24.5 31 26.5V11C27.5 9 23 9.3 18 12C13 9.3 8.5 9 5 11V26.5Z" fill="#ffffff" fill-opacity=".18"/>'
        . '<path d="M5 26.5C8.5 24.5 13 24.8 18 27.5V12C13 9.3 8.5 9 5 11V26.5Z" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<path d="M31 26.5C27.5 24.5 23 24.8 18 27.5V12C23 9.3 27.5 9 31 11V26.5Z" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<line x1="18" y1="12" x2="18" y2="28" stroke="#60a5fa" stroke-width="1.8" stroke-linecap="round"/>'
        . '<path d="M18 4L28 9L18 14L8 9L18 4Z" fill="url(#mft-cap-grad)" stroke="#e0f2fe" stroke-width="1.2" stroke-linejoin="round"/>'
        . '<path d="M26 10V16.5C26 17 25 17.5 25 18" stroke="#f87171" stroke-width="1.5" stroke-linecap="round"/>'
        . '<circle cx="25" cy="18.5" r="1.2" fill="#ef4444"/>'
        . '<circle cx="18" cy="8.8" r="1.5" fill="#fbbf24"/>'
        . '</svg>';
}

/** Renders the logo (icon + two-tone wordmark + tagline), shared by every header/footer. */
function render_logo(array $branding, string $variant = 'dark'): string
{
    $primaryColor = $variant === 'dark' ? '#fff' : 'var(--slate-900)';
    $subtitleColor = $variant === 'dark' ? 'rgba(219,234,254,.65)' : 'var(--slate-500)';
    $split = split_site_name($branding['siteName']);
    $hasComSuffix = (bool) preg_match('/\.com$/i', $branding['siteName']);

    $iconHtml = !empty($branding['logoUrl'])
        ? '<img src="' . e($branding['logoUrl']) . '" alt="" style="width:2.35rem;height:2.35rem;border-radius:8px;object-fit:cover;">'
        : '<span class="logo-icon">' . logo_mark_svg() . '</span>';

    $wordmark = '<span style="color:' . $primaryColor . '">' . e($split['first']) . '</span>';
    if ($split['rest']) {
        $wordmark .= '<span style="color:var(--amber-500)">' . e($split['rest']) . '</span>';
    }
    if ($hasComSuffix) {
        $wordmark .= '<span style="color:' . $primaryColor . '">.com</span>';
    }

    return '<a href="/index.php" class="logo">' . $iconHtml
        . '<span class="logo-text">'
        . '<span class="name">' . $wordmark . '</span>'
        . '<span class="tagline" style="color:' . $subtitleColor . '">' . e($branding['tagline']) . '</span>'
        . '</span></a>';
}

/** Minimal inline-SVG icon set (stroke-based, Feather/Lucide-style paths) — no icon library needed. */
function icon(string $name, string $class = ''): string
{
    $paths = [
        'book' => '<path d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v15H6.5A2.5 2.5 0 0 0 4 19.5Z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'heart' => '<path d="M19 14c1.5-1.5 3-3.5 3-6a5 5 0 0 0-9-3 5 5 0 0 0-9 3c0 2.5 1.5 4.5 3 6l6 6Z"/>',
        'shopping-bag' => '<path d="M6 2 3 7v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7l-3-5"/><path d="M3 7h18"/><path d="M16 11a4 4 0 0 1-8 0"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.9L12 17.8 5.8 21l1.2-6.9-5-4.9 6.9-1L12 2Z"/>',
        'shield' => '<path d="M12 2 4 5v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V5l-8-3Z"/>',
        'truck' => '<path d="M2 8h11v9H2z"/><path d="M13 11h4l4 4v2h-8z"/><circle cx="6.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/>',
        'piggy' => '<path d="M12 5c-5 0-9 3-9 7 0 2 1 3.7 2.5 4.8V19h3v-1.2c.8.14 1.6.2 2.5.2s1.7-.06 2.5-.2V19h3v-2.2C18 15.7 19 14 19 12c0-1-.3-1.9-.9-2.7l1.4-2.3-2.4.5A9.9 9.9 0 0 0 12 5Z"/><circle cx="9" cy="11" r=".8" fill="currentColor" stroke="none"/>',
        'zap' => '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4"/><path d="M12 8v13M19 12v9H5v-9"/><path d="M12 8c-1.5 0-3-1-3-2.5S10 3 12 5c0-2 1.5-3.5 3-2.5S13.5 8 12 8Z"/>',
        'sparkles' => '<path d="m12 3 1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z"/>',
        'message' => '<path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5c-1.4 0-2.7-.3-3.9-.9L3 21l1.9-5.6C4.3 14.2 4 12.9 4 11.5 4 6.8 8.3 3 12.5 3S21 6.8 21 11.5Z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'trash' => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'package' => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'badge-check' => '<path d="M12 2 9.5 4H6v3.5L4 10l2 2.5V16h3.5L12 18l2.5-2H18v-3.5L20 10l-2-2.5V4h-3.5L12 2Z"/><path d="m9 10 2 2 4-4"/>',
        'gavel' => '<path d="m14 13-7.5 7.5a1 1 0 0 1-1.4-1.4L12.6 11.5"/><path d="m16 8 4 4"/><path d="m19 5 3 3"/><path d="M9 4 4 9l4 4 5-5-4-4Z"/>',
        'layout-grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4 20-7Z"/>',
        'download' => '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M21 21H3"/>',
        'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
        'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h3v-4Z"/>',
        'alert-triangle' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'file-bar' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 17v-3M12 17v-6M15 17v-2"/>',
        'palette' => '<circle cx="12" cy="12" r="10"/><circle cx="7.5" cy="10.5" r="1.2" fill="currentColor" stroke="none"/><circle cx="12" cy="7" r="1.2" fill="currentColor" stroke="none"/><circle cx="16.5" cy="10.5" r="1.2" fill="currentColor" stroke="none"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 5v5h5"/><path d="M12 7v5l4 2"/>',
        'toggle' => '<rect x="2" y="6" width="20" height="12" rx="6"/><circle cx="8" cy="12" r="3.5" fill="currentColor" stroke="none"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'landmark' => '<path d="M3 21h18M3 10h18M5 6l7-4 7 4M4 10v11m16-11v11M9 10v11m6-11v11"/>',
        'school' => '<path d="m2 8 10-6 10 6-10 6-10-6Z"/><path d="M6 10.6V17c0 1 2.7 3 6 3s6-2 6-3v-6.4"/>',
        'user-group' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.9 3.1-7 7-7s7 3.1 7 7"/><circle cx="17.5" cy="9" r="2.8"/><path d="M16 13.3c2.9.4 5 2.9 5 5.7"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'phone' => '<path d="M4 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L14 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 2 6a2 2 0 0 1 2-2Z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 6 10 7 10-7"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-3.9M8.6 13.5l6.8 3.9"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'graduation-cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/><path d="M22 10v6"/>',
        'flask' => '<path d="M9 2v6L4 18a2 2 0 0 0 1.8 3h12.4a2 2 0 0 0 1.8-3L15 8V2"/><path d="M8.5 2h7"/><path d="M6.5 14h11"/>',
        'puzzle' => '<path d="M4 9h3a1 1 0 0 0 1-1V5.5a1.5 1.5 0 0 1 3 0V8a1 1 0 0 0 1 1h3v3a1 1 0 0 1-1 1h-2.5a1.5 1.5 0 0 0 0 3H14a1 1 0 0 1 1 1v3H4v-3a1 1 0 0 1 1-1h2.5a1.5 1.5 0 0 0 0-3H5a1 1 0 0 1-1-1V9Z"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg class="' . e($class) . '" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

/** Maps the legacy lucide-react icon names stored on categories to this app's icon() keys. */
function category_icon(?string $dbIcon): string
{
    $map = [
        'BookOpen' => 'book',
        'GraduationCap' => 'graduation-cap',
        'School' => 'school',
        'Baby' => 'heart',
        'HeartHandshake' => 'user-group',
        'FlaskConical' => 'flask',
        'Library' => 'book',
        'Palette' => 'palette',
        'LayoutGrid' => 'layout-grid',
        'NotebookPen' => 'file-bar',
        'Boxes' => 'package',
        'Puzzle' => 'puzzle',
        'Armchair' => 'package',
        'FileStack' => 'file-bar',
        'PackagePlus' => 'package',
    ];
    return $map[$dbIcon ?? ''] ?? 'book';
}

const CATEGORY_ACCENT_TONES = ['royal', 'emerald', 'amber', 'violet', 'teal', 'red'];

/** A stable color tone per category (by slug), so the same category always shows the same
 * accent everywhere on the site — category cards, listing chips, etc. */
function category_accent(?string $slug): string
{
    if (!$slug) {
        return CATEGORY_ACCENT_TONES[0];
    }
    return CATEGORY_ACCENT_TONES[crc32($slug) % count(CATEGORY_ACCENT_TONES)];
}
