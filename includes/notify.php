<?php
/**
 * Notices to the admin when a poster creates, edits, pauses or deletes a
 * listing, or the admin deletes one.
 *
 * Until October 2026 these were one line each ("User x updated their sublet
 * post."), which said that something changed but never what. Each notice now
 * carries the listing itself: all of it when it is new, the fields that
 * changed (before and after) when it is edited, and what it was when it goes.
 * A save that changes nothing sends nothing.
 *
 * Built from snapshots (listing_snapshot()), plain arrays, so the samples in
 * notify_samples.php can render the same notices without a database. Sent as
 * HTML from the same address as the broadcast email (app/api/email.php), with
 * the subject RFC 2047-encoded and the body quoted-printable, so a curly quote
 * or a long description line cannot trip the UVM relay.
 */
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/listing_fields.php';
require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/share.php';

const ADMIN_NOTICE_TO = 'aperkel@uvm.edu';
const ADMIN_NOTICE_FROM = 'UVM Sublets <aperkel@uvm.edu>';

/** Longest stretch of a description a notice quotes. */
const ADMIN_NOTICE_TEXT_LIMIT = 1200;

/**
 * Everything a notice says about a listing: the row, every semester it runs
 * for (names, in calendar order) and its photo count. Null if it is gone.
 */
function listing_snapshot(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM sublets WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $semesters = listing_semesters($pdo, [$id])[$id] ?? [];
    $row['semester_names'] = $semesters ? array_column($semesters, 'name') : [$row['semester']];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sublet_images WHERE sublet_id = ?');
    $stmt->execute([$id]);
    $row['photo_count'] = (int)$stmt->fetchColumn();
    return $row;
}

/**
 * A listing as label => readable value, in the order the post form asks for
 * them. Blank values read "—" so a notice can show something being cleared.
 */
function notice_fields(array $s): array {
    $money = static fn($v) => ($v === null || $v === '') ? '—' : '$' . number_format((float)$v);
    $count = static fn($v) => ($v === null || $v === '') ? '—' : (string)(0 + $v);
    $utility = static fn($v) => ['landlord' => 'Included in rent', 'tenant' => 'Tenant pays'][$v ?? ''] ?? 'Not specified';

    $amenities = [];
    foreach (LISTING_CARD_TAGS as $col => $tag) {
        if (!empty($s[$col])) {
            $amenities[] = $tag['label'];
        }
    }

    $price = $money($s['price'] ?? null) . '/mo';
    if (!empty($s['price_negotiable'])) {
        $price .= ' (or best offer)';
    }

    return [
        'Price'               => $price,
        'Semesters'           => semester_label($s['semester_names'] ?? [$s['semester'] ?? '']) ?: '—',
        'Address'             => trim((string)($s['address'] ?? '')) ?: '—',
        'Map pin'             => isset($s['lat'], $s['lon']) ? sprintf('%.5f, %.5f', $s['lat'], $s['lon']) : '—',
        'Bedrooms'            => $count($s['bedrooms'] ?? null),
        'Bathrooms'           => isset($s['bathrooms']) && $s['bathrooms'] !== '' ? format_half((float)$s['bathrooms']) : '—',
        'Roommates staying'   => $count($s['roommates'] ?? null),
        // The form's own wording, blank included ("Open to anyone").
        'Who lives there'     => ROOMMATE_GENDER_OPTIONS[(string)($s['roommate_gender'] ?? '')] ?? (string)$s['roommate_gender'],
        'Hoping to sublet to' => ROOMMATE_PREFERENCE_OPTIONS[(string)($s['roommate_preference'] ?? '')] ?? (string)$s['roommate_preference'],
        'Electric'            => $utility($s['utility_electric'] ?? null),
        'Gas'                 => $utility($s['utility_gas'] ?? null),
        'Water'               => $utility($s['utility_water'] ?? null),
        'Internet'            => $utility($s['utility_internet'] ?? null),
        'Utilities estimate'  => (float)($s['utility_cost'] ?? 0) > 0 ? $money($s['utility_cost']) . '/mo' : '—',
        'Amenities'           => $amenities ? implode(', ', $amenities) : '—',
        'Name shown'          => trim((string)($s['display_name'] ?? '')) ?: '— (shows the NetID)',
        'Contact email'       => trim((string)($s['contact_email'] ?? '')) ?: '—',
        'Phone'               => trim((string)($s['contact_phone'] ?? '')) ?: '—',
        'Description'         => trim((string)($s['description'] ?? '')) ?: '—',
        'Photos'              => (string)($s['photo_count'] ?? 0),
    ];
}

/** One line that identifies a listing in a subject or a heading. */
function notice_listing_line(array $s): string {
    return '$' . number_format((float)($s['price'] ?? 0)) . '/mo · '
        . format_address((string)($s['address'] ?? ''))
        . ' (' . ($s['username'] ?? '?') . ')';
}

/**
 * The fields that differ between two snapshots, as [label, before, after].
 * Photos are left to the caller, which knows how many were added; a moved pin
 * is reported by how far it moved rather than by coordinates.
 */
function notice_changes(array $before, array $after): array {
    $a = notice_fields($before);
    $b = notice_fields($after);
    $changes = [];
    foreach ($a as $label => $old) {
        if ($label === 'Photos' || $label === 'Map pin') {
            continue;
        }
        if ($old !== $b[$label]) {
            $changes[] = [$label, $old, $b[$label]];
        }
    }
    if (isset($before['lat'], $before['lon'], $after['lat'], $after['lon'])) {
        $miles = notice_miles((float)$before['lat'], (float)$before['lon'], (float)$after['lat'], (float)$after['lon']);
        if ($miles >= 0.01) {
            $changes[] = ['Map pin', $a['Map pin'], $b['Map pin'] . sprintf(' (moved %.2f mi)', $miles)];
        }
    }
    return $changes;
}

function notice_miles(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 3959 * 2 * asin(min(1, sqrt($h)));
}

/** A new listing: all of it. */
function admin_notice_created(array $s): array {
    return [
        'subject' => 'New listing: ' . notice_listing_line($s),
        'heading' => 'New listing',
        'intro' => ($s['username'] ?? '?') . ' posted a listing.',
        'table' => array_map(null, array_keys(notice_fields($s)), array_values(notice_fields($s))),
        'columns' => ['', ''],
        'id' => (int)($s['id'] ?? 0),
    ];
}

/**
 * An edit: the fields that changed, before and after, and any photos added.
 * Null when the save changed nothing, so no notice goes out.
 */
function admin_notice_updated(array $before, array $after, int $photosAdded): ?array {
    $changes = notice_changes($before, $after);
    if ($photosAdded > 0) {
        $changes[] = ['Photos', (string)($before['photo_count'] ?? 0), ($after['photo_count'] ?? 0) . " ($photosAdded added)"];
    }
    if (!$changes) {
        return null;
    }
    $labels = array_column($changes, 0);
    $what = strtolower(implode(', ', array_slice($labels, 0, 3))) . (count($labels) > 3 ? ' and ' . (count($labels) - 3) . ' more' : '');
    return [
        'subject' => 'Listing edited (' . $what . '): ' . notice_listing_line($after),
        'heading' => 'Listing edited',
        'intro' => ($after['username'] ?? '?') . ' changed ' . count($changes) . ' thing' . (count($changes) === 1 ? '' : 's') . ' on their listing.',
        'table' => $changes,
        'columns' => ['', 'Before', 'After'],
        'id' => (int)($after['id'] ?? 0),
    ];
}

/** Paused, taken, or back up. */
function admin_notice_status(array $s, string $from, string $to): array {
    $word = ['open' => 'put back up', 'paused' => 'paused', 'taken' => 'marked taken'][$to] ?? $to;
    return [
        'subject' => 'Listing ' . $word . ': ' . notice_listing_line($s),
        'heading' => 'Listing ' . $word,
        'intro' => ($s['username'] ?? '?') . ' changed their listing from ' . (LISTING_STATUSES[$from] ?? $from) . ' to ' . (LISTING_STATUSES[$to] ?? $to) . '.',
        'table' => array_map(null, ['Price', 'Semesters', 'Address', 'Posted'], [
            notice_fields($s)['Price'], notice_fields($s)['Semesters'], notice_fields($s)['Address'],
            !empty($s['posted_at']) ? date('M j, Y', strtotime($s['posted_at'])) : '—',
        ]),
        'columns' => ['', ''],
        'id' => (int)($s['id'] ?? 0),
    ];
}

/** A deleted listing: what it was. $by is 'poster' or 'admin'. */
function admin_notice_deleted(array $s, string $by): array {
    $fields = notice_fields($s);
    $rows = [];
    foreach (['Price', 'Semesters', 'Address', 'Bedrooms', 'Roommates staying', 'Contact email', 'Photos', 'Description'] as $label) {
        $rows[] = [$label, $fields[$label]];
    }
    $rows[] = ['Status', LISTING_STATUSES[$s['status'] ?? 'open'] ?? ($s['status'] ?? 'open')];
    $rows[] = ['Posted', !empty($s['posted_at']) ? date('M j, Y', strtotime($s['posted_at'])) : '—'];
    return [
        'subject' => 'Listing deleted' . ($by === 'admin' ? ' by you' : '') . ': ' . notice_listing_line($s),
        'heading' => 'Listing deleted' . ($by === 'admin' ? ' (by you, from the Posts tab)' : ''),
        'intro' => $by === 'admin'
            ? 'You deleted ' . ($s['username'] ?? '?') . "'s listing and its photos."
            : ($s['username'] ?? '?') . ' deleted their listing and its photos.',
        'table' => $rows,
        'columns' => ['', ''],
        'id' => 0,
    ];
}

/** The notice as an HTML email, styled like the broadcast template. */
function admin_notice_html(array $n): string {
    $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $cell = static function ($v) use ($e) {
        $v = (string)$v;
        if (mb_strlen($v) > ADMIN_NOTICE_TEXT_LIMIT) {
            $v = mb_substr($v, 0, ADMIN_NOTICE_TEXT_LIMIT) . '…';
        }
        return nl2br($e($v));
    };
    $th = 'style="text-align:left;padding:6px 10px;border-bottom:2px solid #dce1e3;font-size:12px;color:#5f7378;text-transform:uppercase;letter-spacing:0.04em"';
    $tdLabel = 'style="padding:8px 10px;border-bottom:1px solid #eef1f2;vertical-align:top;font-weight:700;white-space:nowrap;color:#4a5e63"';
    $td = 'style="padding:8px 10px;border-bottom:1px solid #eef1f2;vertical-align:top"';
    $tdOld = 'style="padding:8px 10px;border-bottom:1px solid #eef1f2;vertical-align:top;color:#b5401a;background:#fce8e3"';
    $tdNew = 'style="padding:8px 10px;border-bottom:1px solid #eef1f2;vertical-align:top;color:#1a6b4a;background:#e8f5e9"';

    $diff = count($n['columns']) === 3;
    $rows = '';
    if ($diff) {
        $rows .= "<tr><th $th></th><th $th>{$e($n['columns'][1])}</th><th $th>{$e($n['columns'][2])}</th></tr>\n";
    }
    foreach ($n['table'] as $r) {
        $rows .= "<tr><td $tdLabel>{$e($r[0])}</td>"
            . ($diff ? "<td $tdOld>{$cell($r[1])}</td><td $tdNew>{$cell($r[2])}</td>" : "<td $td>{$cell($r[1])}</td>")
            . "</tr>\n";
    }

    $links = '';
    if (!empty($n['id'])) {
        $links = '<a href="' . $e(SHARE_ORIGIN . '/app/index.php?id=' . (int)$n['id']) . '" style="color:#154734;font-weight:700">Open the listing</a> &middot; ';
    }
    $links .= '<a href="' . $e(SHARE_ORIGIN . '/app/admin.php#posts') . '" style="color:#154734;font-weight:700">Admin: Posts</a>';
    $when = date('D M j, g:i a');

    return <<<HTML
<html>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#00313C;max-width:680px;margin:0 auto;">
<div style="background:#154734;padding:14px 20px;border-radius:8px 8px 0 0;">
<span style="color:#FFD100;font-weight:700;font-size:16px;">UVM Sublets</span>
<span style="color:#c5d6ce;font-size:13px;"> &middot; admin notice</span>
</div>
<div style="padding:20px;background:#ffffff;border:1px solid #e0e4e5;border-top:0;">
<h1 style="margin:0 0 4px;font-size:20px;color:#154734;">{$e($n['heading'])}</h1>
<p style="margin:0 0 16px;color:#4a5e63;font-size:14px;">{$e($n['intro'])} <span style="color:#5f7378;">{$e($when)}</span></p>
<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.45;">
{$rows}</table>
<p style="margin:16px 0 0;font-size:14px;">{$links}</p>
</div>
</body>
</html>
HTML;
}

/** Send a notice to the admin. Returns mail()'s result. */
function admin_notify(?array $n): bool {
    if ($n === null) {
        return false;
    }
    $subject = str_replace(["\r", "\n"], ' ', '[UVM Sublets] ' . $n['subject']);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'From: ' . ADMIN_NOTICE_FROM,
    ];
    return mail(
        ADMIN_NOTICE_TO,
        mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"),
        quoted_printable_encode(admin_notice_html($n)),
        implode("\r\n", $headers)
    );
}
