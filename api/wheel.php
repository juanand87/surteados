<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/wheel_helper.php';

$pdo = db();
surteados_ensure_wheel_tables($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'admin') {
        auth_required();
        $settings = get_settings(['wheel_enabled']);
        $prizes = $pdo->query('SELECT * FROM wheel_prizes ORDER BY display_order ASC, created_at ASC')->fetchAll();
        $history = $pdo->query("SELECT ws.*, wp.title AS prize_title, dc.code, dc.status AS code_status
            FROM wheel_spins ws
            LEFT JOIN wheel_prizes wp ON wp.id = ws.prize_id
            LEFT JOIN discount_codes dc ON dc.id = ws.discount_code_id
            ORDER BY ws.created_at DESC
            LIMIT 80")->fetchAll();
        json_ok([
            'enabled' => (($settings['wheel_enabled'] ?? '0') === '1'),
            'prizes' => array_map('surteados_wheel_public_prize', $prizes),
            'probabilityTotal' => surteados_wheel_probability_total($prizes),
            'history' => $history,
        ]);
    }

    $settings = get_settings(['wheel_enabled']);
    $enabled = (($settings['wheel_enabled'] ?? '0') === '1');
    $stmt = $pdo->query('SELECT * FROM wheel_prizes WHERE active = 1 ORDER BY display_order ASC, created_at ASC');
    $prizes = $stmt->fetchAll();
    json_ok([
        'enabled' => $enabled,
        'prizes' => array_map('surteados_wheel_public_prize', $prizes),
        'probabilityTotal' => surteados_wheel_probability_total($prizes),
    ]);
}

if ($method !== 'POST') json_error('Method not allowed', 405);
$b = body();
$action = $b['action'] ?? $action;

if ($action === 'settings') {
    auth_required();
    $enabled = !empty($b['enabled']) ? '1' : '0';
    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('wheel_enabled', ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$enabled]);
    json_ok(['enabled' => $enabled === '1']);
}

if ($action === 'save_prize') {
    auth_required();
    $id = trim((string)($b['id'] ?? ''));
    if ($id === '') $id = generate_id('wp');
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') json_error('Ingresa el nombre del premio.');

    $prizeType = trim((string)($b['prizeType'] ?? 'percent'));
    $discountType = trim((string)($b['discountType'] ?? 'none'));
    $allowedPrize = ['percent','fixed','physical','none','custom'];
    $allowedDiscount = ['percent','fixed','none'];
    if (!in_array($prizeType, $allowedPrize, true)) $prizeType = 'custom';
    if (!in_array($discountType, $allowedDiscount, true)) $discountType = 'none';

    $probability = max(0, min(100, (float)($b['probability'] ?? 0)));
    $discountValue = max(0, (int)($b['discountValue'] ?? 0));
    if ($discountType === 'percent') $discountValue = min(100, $discountValue);

    $stmt = $pdo->prepare("INSERT INTO wheel_prizes
      (id,title,description,prize_type,discount_type,discount_value,probability,code_prefix,active,display_order,color1,color2)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), prize_type=VALUES(prize_type),
        discount_type=VALUES(discount_type), discount_value=VALUES(discount_value), probability=VALUES(probability),
        code_prefix=VALUES(code_prefix), active=VALUES(active), display_order=VALUES(display_order), color1=VALUES(color1), color2=VALUES(color2)");
    $stmt->execute([
        $id,
        $title,
        trim((string)($b['description'] ?? '')),
        $prizeType,
        $discountType,
        $discountValue,
        $probability,
        trim((string)($b['codePrefix'] ?? 'RULETA')) ?: 'RULETA',
        !empty($b['active']) ? 1 : 0,
        (int)($b['displayOrder'] ?? 0),
        trim((string)($b['color1'] ?? '#7c3aed')) ?: '#7c3aed',
        trim((string)($b['color2'] ?? '#f59e0b')) ?: '#f59e0b',
    ]);
    json_ok(['id' => $id]);
}

if ($action === 'delete_prize') {
    auth_required();
    $id = trim((string)($b['id'] ?? ''));
    if ($id === '') json_error('ID requerido.');
    $pdo->prepare('DELETE FROM wheel_prizes WHERE id = ?')->execute([$id]);
    json_ok(['deleted' => true]);
}

if ($action === 'spin') {
    $settings = get_settings(['wheel_enabled']);
    if (($settings['wheel_enabled'] ?? '0') !== '1') json_error('La ruleta no está disponible en este momento.');

    $email = surteados_wheel_email((string)($b['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Ingresa un correo válido.');

    $check = $pdo->prepare('SELECT ws.*, wp.title AS prize_title FROM wheel_spins ws LEFT JOIN wheel_prizes wp ON wp.id = ws.prize_id WHERE ws.email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) json_error('Este correo ya participó en la ruleta.');

    $prizes = $pdo->query('SELECT * FROM wheel_prizes WHERE active = 1 ORDER BY display_order ASC, created_at ASC')->fetchAll();
    if (!$prizes) json_error('La ruleta aún no tiene premios configurados.');
    $total = surteados_wheel_probability_total($prizes);
    if (abs($total - 100.0) > 0.01) json_error('La ruleta no está lista: las probabilidades activas deben sumar 100%.');

    try {
        $prize = surteados_wheel_pick_prize($prizes);
        $code = '';
        $codeId = null;
        $pdo->beginTransaction();

        if (($prize['discount_type'] ?? 'none') !== 'none' && (int)($prize['discount_value'] ?? 0) > 0) {
            $code = surteados_generate_discount_code($pdo, (string)($prize['code_prefix'] ?? 'RULETA'));
            $codeId = generate_id('dc');
            $pdo->prepare("INSERT INTO discount_codes
              (id,code,source,prize_id,email,discount_type,discount_value,status,expires_at)
              VALUES (?,?,?,?,?,?,?,'active',DATE_ADD(NOW(), INTERVAL 30 DAY))")
              ->execute([$codeId, $code, 'wheel', $prize['id'], $email, $prize['discount_type'], (int)$prize['discount_value']]);
        }

        $spinId = generate_id('ws');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $pdo->prepare('INSERT INTO wheel_spins (id,email,prize_id,discount_code_id,ip_hash,user_agent) VALUES (?,?,?,?,?,?)')
            ->execute([$spinId, $email, $prize['id'], $codeId, surteados_wheel_ip_hash(), $ua]);
        $pdo->commit();

        $mailSent = surteados_send_wheel_email($pdo, $email, $prize, $code);
        json_ok([
            'spinId' => $spinId,
            'prize' => surteados_wheel_public_prize($prize),
            'hasCode' => $code !== '',
            'mailSent' => $mailSent,
            'mailError' => $mailSent ? '' : surteados_last_email_error(),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('No se pudo completar el giro: ' . $e->getMessage(), 500);
    }
}

json_error('Acción no válida.');