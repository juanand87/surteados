<?php
/**
 * SURTEADOS — Public page data helpers
 * Normalises DB rows (snake_case) → camelCase for app.js / window.SURTEADOS_DATA
 */

function normalizeRaffle(array $r, PDO $pdo): array {
    $ps = $pdo->prepare('SELECT * FROM raffle_prizes WHERE raffle_id = ? ORDER BY place');
    $ps->execute([$r['id']]);
    $prizes = $ps->fetchAll();

    $pk = $pdo->prepare('SELECT * FROM raffle_packs WHERE raffle_id = ? ORDER BY id');
    $pk->execute([$r['id']]);
    $packs = $pk->fetchAll();

    return [
        'id'           => $r['id'],
        'title'        => $r['title'],
        'category'     => $r['category'] ?? '',
        'description'  => $r['description'] ?? '',
        'status'       => $r['status'] ?? 'soon',
        'drawDate'     => $r['draw_date'] ?? null,
        'image'        => $r['image_url'] ?: null,
        'imageEmoji'   => $r['image_emoji'] ?: '🎁',
        'totalTickets' => (int)($r['total_tickets'] ?? 0) > 0 ? (int)$r['total_tickets'] : null,
        'soldTickets'  => (int)($r['sold_tickets'] ?? 0),
        'featured'     => (bool)($r['featured'] ?? false),
        'meetLink'     => $r['meet_link'] ?? null,
        'legalInfo'    => [
            'organizer'   => $r['legal_organizer'] ?? '',
            'rut'         => $r['legal_rut'] ?? '',
            'notary'      => $r['legal_notary'] ?? '',
            'certificate' => $r['legal_certificate'] ?? '',
            'salesPeriod' => $r['legal_sales_period'] ?? '',
        ],
        'prizes' => array_map(fn($p) => [
            'id'    => $p['id'],
            'place' => (int)$p['place'],
            'name'  => $p['name'],
            'value' => (int)$p['value'],
            'emoji' => $p['emoji'] ?: '🎁',
            'image' => $p['image_url'] ?: null,
        ], $prizes),
        'packs' => array_map(fn($pk) => [
            'id'            => $pk['id'],
            'label'         => $pk['label'],
            'qty'           => (int)$pk['qty'],
            'price'         => (int)$pk['price'],
            'originalPrice' => (int)$pk['original_price'],
            'discount'      => (int)($pk['discount'] ?? 0),
            'bestValue'     => (bool)($pk['best_value'] ?? false),
        ], $packs),
    ];
}

function normalizeWinner(array $w): array {
    return [
        'id'             => $w['id'],
        'raffleId'       => $w['raffle_id'],
        'raffleTitle'    => $w['raffle_title'] ?? '',
        'winnerName'     => $w['winner_name'] ?? '',
        'winnerLocation' => $w['winner_location'] ?? '',
        'prize'          => $w['prize'] ?? '',
        'prizeValue'     => (int)($w['prize_value'] ?? 0),
        'drawDate'       => $w['draw_date'] ?? null,
        'ticketNumber'   => $w['ticket_number'] ?? '',
        'edition'        => $w['edition'] ?? '',
        'emoji'          => $w['emoji'] ?: '🏆',
        'verified'       => (bool)($w['verified'] ?? false),
        'videoUrl'       => $w['video_url'] ?: null,
        'notaryDoc'      => $w['notary_doc'] ?: null,
        'image'          => $w['winner_image_url'] ?? null,
    ];
}

function getPublicSettings(PDO $pdo): array {
    $rows = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll();
    $s = [];
    foreach ($rows as $r) {
        $s[$r['key']] = $r['value'];
    }
    return [
        'siteName'          => $s['site_name']          ?? 'Surteados',
        'logo'              => $s['site_logo']           ?? null,
        'theme'             => [
            'primary'      => $s['theme_primary']       ?? '#7c3aed',
            'primaryLight' => $s['theme_primary_light'] ?? '#9d5cf6',
            'primaryDark'  => $s['theme_primary_dark']  ?? '#5b21b6',
            'accent'       => $s['theme_accent']        ?? '#f59e0b',
            'accentLight'  => $s['theme_accent_light']  ?? '#fbbf24',
            'accentDark'   => $s['theme_accent_dark']   ?? '#d97706',
        ],
        'heroSliderEnabled' => !empty($s['hero_slider_enabled']),
        'heroSlides'        => json_decode($s['hero_slides'] ?? '[]', true) ?: [],
        'developmentMode'    => !empty($s['development_mode_enabled']),
        'ticketLabel'       => $s['ticket_label']        ?? 'imagen',
        'ticketLabelPlural' => $s['ticket_label_plural']  ?? 'imagenes',
    ];
}

function getPublicData(PDO $pdo): array {
    $raffles = array_map(
        fn($r) => normalizeRaffle($r, $pdo),
        $pdo->query('SELECT * FROM raffles ORDER BY featured DESC, created_at DESC')->fetchAll()
    );
    $winners = array_map(
        'normalizeWinner',
        $pdo->query('SELECT * FROM winners ORDER BY draw_date DESC')->fetchAll()
    );
    return [
        'raffles'  => $raffles,
        'winners'  => $winners,
        'settings' => getPublicSettings($pdo),
    ];
}
