<?php
/** First-party analytics collector and protected admin reports. */
require __DIR__ . '/config.php';
require_once __DIR__ . '/analytics_helper.php';

$pdo = db();
surteados_ensure_analytics_tables($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    auth_required();
    $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
    $since = "DATE_SUB(NOW(), INTERVAL {$days} DAY)";

    $pdo->exec("UPDATE analytics_carts
                   SET status = 'abandoned', abandoned_at = COALESCE(abandoned_at, NOW())
                 WHERE status = 'active'
                   AND item_count > 0
                   AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");

    $summary = $pdo->query("SELECT
        COUNT(*) AS sessions,
        COUNT(DISTINCT visitor_id) AS visitors,
        COALESCE(SUM(page_views),0) AS page_views,
        COALESCE(ROUND(AVG(active_seconds)),0) AS avg_active_seconds
      FROM analytics_sessions WHERE first_seen_at >= {$since}")->fetch();

    $live = (int)$pdo->query("SELECT COUNT(DISTINCT session_id) FROM analytics_sessions
      WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)")->fetchColumn();

    $daily = $pdo->query("SELECT DATE(first_seen_at) AS day,
        COUNT(*) AS sessions,
        COUNT(DISTINCT visitor_id) AS visitors,
        COALESCE(SUM(page_views),0) AS page_views,
        COALESCE(ROUND(AVG(active_seconds)),0) AS avg_seconds
      FROM analytics_sessions
      WHERE first_seen_at >= {$since}
      GROUP BY DATE(first_seen_at)
      ORDER BY day ASC")->fetchAll();

    $cartSummary = $pdo->query("SELECT
        SUM(status = 'abandoned') AS abandoned,
        SUM(status = 'abandoned' AND has_contact = 1) AS abandoned_with_contact,
        SUM(status = 'abandoned' AND has_contact = 0) AS abandoned_without_contact,
        SUM(status = 'converted') AS converted,
        SUM(status = 'active') AS active
      FROM analytics_carts WHERE created_at >= {$since}")->fetch() ?: [];

    $funnelRows = $pdo->query("SELECT event_type, COUNT(DISTINCT session_id) AS total
      FROM analytics_events
      WHERE created_at >= {$since}
        AND event_type IN ('raffle_view','purchase_open','pack_selected','details_completed','payment_started','purchase_completed')
      GROUP BY event_type")->fetchAll();
    $funnel = array_fill_keys(['raffle_view','purchase_open','pack_selected','details_completed','payment_started','purchase_completed'], 0);
    foreach ($funnelRows as $row) $funnel[$row['event_type']] = (int)$row['total'];

    $raffles = $pdo->query("SELECT pv.raffle_id,
        COALESCE(r.title, pv.raffle_id) AS title,
        COUNT(*) AS views,
        COUNT(DISTINCT pv.session_id) AS unique_visitors,
        (SELECT COUNT(*) FROM tickets t WHERE t.raffle_id = pv.raffle_id AND t.payment_status = 'paid' AND t.purchase_date >= {$since}) AS purchases,
        (SELECT COALESCE(SUM(t.amount),0) FROM tickets t WHERE t.raffle_id = pv.raffle_id AND t.payment_status = 'paid' AND t.purchase_date >= {$since}) AS revenue
      FROM analytics_page_views pv
      LEFT JOIN raffles r ON r.id = pv.raffle_id
      WHERE pv.raffle_id IS NOT NULL AND pv.started_at >= {$since}
      GROUP BY pv.raffle_id, r.title
      ORDER BY views DESC")->fetchAll();
    foreach ($raffles as &$raffle) {
        $raffle['views'] = (int)$raffle['views'];
        $raffle['unique_visitors'] = (int)$raffle['unique_visitors'];
        $raffle['purchases'] = (int)$raffle['purchases'];
        $raffle['revenue'] = (int)$raffle['revenue'];
        $raffle['conversion'] = $raffle['unique_visitors'] > 0
            ? round(($raffle['purchases'] / $raffle['unique_visitors']) * 100, 1)
            : 0;
    }
    unset($raffle);

    $devices = $pdo->query("SELECT device_type, COUNT(*) AS sessions
      FROM analytics_sessions WHERE first_seen_at >= {$since}
      GROUP BY device_type ORDER BY sessions DESC")->fetchAll();

    $pages = $pdo->query("SELECT path, COUNT(*) AS views, COUNT(DISTINCT session_id) AS visitors,
        COALESCE(ROUND(AVG(active_seconds)),0) AS avg_seconds
      FROM analytics_page_views WHERE started_at >= {$since}
      GROUP BY path ORDER BY views DESC LIMIT 15")->fetchAll();

    $abandoned = $pdo->query("SELECT cart_id, has_contact, buyer_name, buyer_email, buyer_phone,
        item_count, total_amount, current_step, items_json, updated_at, abandoned_at
      FROM analytics_carts
      WHERE status = 'abandoned' AND created_at >= {$since}
      ORDER BY COALESCE(abandoned_at, updated_at) DESC LIMIT 100")->fetchAll();
    foreach ($abandoned as &$cart) {
        $cart['items'] = json_decode($cart['items_json'] ?? '[]', true) ?: [];
        unset($cart['items_json']);
    }
    unset($cart);

    $sales = $pdo->query("SELECT COUNT(*) AS paid_rows, COALESCE(SUM(amount),0) AS revenue,
        COUNT(DISTINCT buyer_email) AS buyers
      FROM tickets WHERE payment_status = 'paid' AND purchase_date >= {$since}")->fetch();

    json_ok([
        'days' => $days,
        'summary' => [
            'sessions' => (int)($summary['sessions'] ?? 0),
            'visitors' => (int)($summary['visitors'] ?? 0),
            'pageViews' => (int)($summary['page_views'] ?? 0),
            'avgActiveSeconds' => (int)($summary['avg_active_seconds'] ?? 0),
            'live' => $live,
        ],
        'carts' => [
            'abandoned' => (int)($cartSummary['abandoned'] ?? 0),
            'withContact' => (int)($cartSummary['abandoned_with_contact'] ?? 0),
            'withoutContact' => (int)($cartSummary['abandoned_without_contact'] ?? 0),
            'converted' => (int)($cartSummary['converted'] ?? 0),
            'active' => (int)($cartSummary['active'] ?? 0),
        ],
        'sales' => [
            'paidRows' => (int)($sales['paid_rows'] ?? 0),
            'revenue' => (int)($sales['revenue'] ?? 0),
            'buyers' => (int)($sales['buyers'] ?? 0),
        ],
        'daily' => $daily,
        'funnel' => $funnel,
        'raffles' => $raffles,
        'devices' => $devices,
        'pages' => $pages,
        'abandoned' => $abandoned,
    ]);
}

if ($method !== 'POST') json_error('Método no permitido', 405);

$userAgent = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
if ($userAgent !== '' && preg_match('/bot|crawl|spider|slurp|headless|preview|facebookexternalhit|whatsapp/i', $userAgent)) {
    json_ok(['tracked' => false, 'reason' => 'bot']);
}

$b = body();
$action = trim((string)($b['action'] ?? ''));
$sessionId = surteados_analytics_id($b['sessionId'] ?? '');
$visitorId = surteados_analytics_id($b['visitorId'] ?? '');
if ($sessionId === '' || $visitorId === '') json_error('Identificadores inválidos');

if ($action === 'page_start') {
    $viewId = surteados_analytics_id($b['viewId'] ?? '');
    if ($viewId === '') json_error('viewId inválido');
    $path = surteados_analytics_text($b['path'] ?? '/', 255) ?: '/';
    $raffleId = surteados_analytics_text($b['raffleId'] ?? '', 25) ?: null;
    $device = surteados_analytics_text($b['deviceType'] ?? 'desktop', 20);
    if (!in_array($device, ['mobile','tablet','desktop'], true)) $device = 'desktop';
    $referrer = surteados_analytics_text($b['referrer'] ?? '', 500) ?: null;
    $source = surteados_analytics_text($b['utmSource'] ?? '', 100) ?: null;
    $medium = surteados_analytics_text($b['utmMedium'] ?? '', 100) ?: null;
    $campaign = surteados_analytics_text($b['utmCampaign'] ?? '', 150) ?: null;

    $stmt = $pdo->prepare("INSERT INTO analytics_sessions
      (session_id, visitor_id, entry_path, referrer, utm_source, utm_medium, utm_campaign, device_type, ip_hash, page_views, first_seen_at, last_seen_at)
      VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),NOW())
      ON DUPLICATE KEY UPDATE last_seen_at=NOW(), page_views=page_views+1");
    $stmt->execute([$sessionId,$visitorId,$path,$referrer,$source,$medium,$campaign,$device,surteados_analytics_ip_hash()]);

    $stmt = $pdo->prepare("INSERT INTO analytics_page_views
      (view_id, session_id, visitor_id, path, raffle_id, started_at, last_seen_at)
      VALUES (?,?,?,?,?,NOW(),NOW())
      ON DUPLICATE KEY UPDATE last_seen_at=NOW()");
    $stmt->execute([$viewId,$sessionId,$visitorId,$path,$raffleId]);

    if ($raffleId) {
        $stmt = $pdo->prepare("INSERT INTO analytics_events (session_id,visitor_id,event_type,raffle_id,created_at) VALUES (?,?, 'raffle_view', ?, NOW())");
        $stmt->execute([$sessionId,$visitorId,$raffleId]);
    }
    json_ok(['tracked' => true]);
}

if ($action === 'heartbeat') {
    $viewId = surteados_analytics_id($b['viewId'] ?? '');
    $seconds = max(1, min(60, (int)($b['seconds'] ?? 15)));
    $stmt = $pdo->prepare("UPDATE analytics_sessions SET last_seen_at=NOW(), active_seconds=active_seconds+? WHERE session_id=?");
    $stmt->execute([$seconds,$sessionId]);
    if ($viewId !== '') {
        $stmt = $pdo->prepare("UPDATE analytics_page_views SET last_seen_at=NOW(), active_seconds=active_seconds+? WHERE view_id=? AND session_id=?");
        $stmt->execute([$seconds,$viewId,$sessionId]);
    }
    json_ok(['tracked' => true]);
}

if ($action === 'event') {
    $allowed = ['purchase_open','pack_selected','details_started','details_completed','payment_started','payment_redirect','purchase_completed','cart_open'];
    $eventType = surteados_analytics_text($b['eventType'] ?? '', 50);
    if (!in_array($eventType, $allowed, true)) json_error('Evento inválido');
    $raffleId = surteados_analytics_text($b['raffleId'] ?? '', 25) ?: null;
    $cartId = surteados_analytics_id($b['cartId'] ?? '') ?: null;
    $step = isset($b['step']) ? max(0, min(9, (int)$b['step'])) : null;
    $metadata = json_encode(is_array($b['metadata'] ?? null) ? $b['metadata'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($metadata) > 4000) $metadata = '{}';
    $stmt = $pdo->prepare("INSERT INTO analytics_events
      (session_id,visitor_id,event_type,raffle_id,cart_id,step,metadata,created_at)
      VALUES (?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$sessionId,$visitorId,$eventType,$raffleId,$cartId,$step,$metadata]);
    $pdo->prepare("UPDATE analytics_sessions SET last_seen_at=NOW(), converted=IF(?='purchase_completed',1,converted) WHERE session_id=?")
        ->execute([$eventType,$sessionId]);
    if ($eventType === 'purchase_completed') {
        if ($cartId) {
            $pdo->prepare("UPDATE analytics_carts SET status='converted', converted_at=NOW(), updated_at=NOW() WHERE cart_id=?")
                ->execute([$cartId]);
        } else {
            $pdo->prepare("UPDATE analytics_carts SET status='converted', converted_at=NOW(), updated_at=NOW()
              WHERE session_id=? AND status IN ('active','abandoned') ORDER BY updated_at DESC LIMIT 1")
                ->execute([$sessionId]);
        }
    }
    json_ok(['tracked' => true]);
}

if ($action === 'cart') {
    $cartId = surteados_analytics_id($b['cartId'] ?? '');
    if ($cartId === '') json_error('cartId inválido');
    $items = is_array($b['items'] ?? null) ? array_slice($b['items'], 0, 20) : [];
    $cleanItems = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $cleanItems[] = [
            'raffleId' => surteados_analytics_text($item['raffleId'] ?? '', 25),
            'title' => surteados_analytics_text($item['raffleTitle'] ?? $item['title'] ?? '', 180),
            'pack' => surteados_analytics_text($item['packLabel'] ?? $item['pack'] ?? '', 120),
            'price' => max(0, (int)($item['price'] ?? 0)),
        ];
    }
    $name = surteados_analytics_text($b['buyerName'] ?? '', 180);
    $email = filter_var(trim((string)($b['buyerEmail'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
    $phone = surteados_analytics_text($b['buyerPhone'] ?? '', 50);
    $hasContact = ($name !== '' || $email !== '' || $phone !== '') ? 1 : 0;
    $status = surteados_analytics_text($b['status'] ?? 'active', 20);
    if (!in_array($status, ['active','converted','cleared'], true)) $status = 'active';
    if (!$cleanItems && $status === 'active') $status = 'cleared';
    $total = array_sum(array_column($cleanItems, 'price'));
    $step = max(1, min(4, (int)($b['step'] ?? 1)));
    $itemsJson = json_encode($cleanItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $convertedAt = $status === 'converted' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("INSERT INTO analytics_carts
      (cart_id,session_id,visitor_id,status,has_contact,buyer_name,buyer_email,buyer_phone,items_json,item_count,total_amount,current_step,created_at,updated_at,converted_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),?)
      ON DUPLICATE KEY UPDATE session_id=VALUES(session_id), visitor_id=VALUES(visitor_id), status=VALUES(status),
        has_contact=VALUES(has_contact), buyer_name=VALUES(buyer_name), buyer_email=VALUES(buyer_email), buyer_phone=VALUES(buyer_phone),
        items_json=VALUES(items_json), item_count=VALUES(item_count), total_amount=VALUES(total_amount), current_step=VALUES(current_step),
        updated_at=NOW(), converted_at=COALESCE(VALUES(converted_at), converted_at), abandoned_at=IF(VALUES(status)='active',NULL,abandoned_at)");
    $stmt->execute([$cartId,$sessionId,$visitorId,$status,$hasContact,$name ?: null,$email ?: null,$phone ?: null,$itemsJson,count($cleanItems),$total,$step,$convertedAt]);
    json_ok(['tracked' => true]);
}

json_error('Acción inválida');