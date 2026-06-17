<?php

function surteados_ticket_secret(PDO $pdo): string
{
    $key = 'ticket_verification_secret';
    $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $secret = trim((string)$stmt->fetchColumn());
    if ($secret !== '') return $secret;

    $secret = bin2hex(random_bytes(32));
    $ins = $pdo->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $ins->execute([$key, $secret]);
    return $secret;
}

function surteados_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function surteados_b64url_decode(string $data): string|false
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode($data, true);
}

function surteados_ticket_token(PDO $pdo, string $ticketId, string $number, string $orderId, string $email): string
{
    $payload = [
        'tid' => $ticketId,
        'num' => $number,
        'ord' => $orderId,
        'emh' => hash('sha256', strtolower(trim($email))),
        'iat' => time(),
    ];
    $encoded = surteados_b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', $encoded, surteados_ticket_secret($pdo));
    return $encoded . '.' . $sig;
}

function surteados_verify_ticket_token(PDO $pdo, string $token): array
{
    $parts = explode('.', trim($token), 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return ['valid' => false, 'reason' => 'Token incompleto'];
    }

    [$encoded, $sig] = $parts;
    $expected = hash_hmac('sha256', $encoded, surteados_ticket_secret($pdo));
    if (!hash_equals($expected, $sig)) {
        return ['valid' => false, 'reason' => 'Firma inválida'];
    }

    $raw = surteados_b64url_decode($encoded);
    $payload = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($payload) || empty($payload['tid']) || empty($payload['num'])) {
        return ['valid' => false, 'reason' => 'Token inválido'];
    }

    surteados_ensure_flow_order_number_column($pdo);

    $stmt = $pdo->prepare(
        "SELECT t.*, r.title AS raffle_title, r.draw_date, r.image_url AS raffle_image
           FROM tickets t
           LEFT JOIN raffles r ON r.id = t.raffle_id
          WHERE t.id = ?
            AND t.payment_status = 'paid'
          LIMIT 1"
    );
    $stmt->execute([(string)$payload['tid']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return ['valid' => false, 'reason' => 'Compra no encontrada o no pagada'];
    }

    $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?: [];
    $number = (string)$payload['num'];
    if (!in_array($number, array_map('strval', $numbers), true)) {
        return ['valid' => false, 'reason' => 'Número no pertenece a la compra'];
    }

    $emailHash = hash('sha256', strtolower(trim((string)$ticket['buyer_email'])));
    if (!hash_equals($emailHash, (string)($payload['emh'] ?? ''))) {
        return ['valid' => false, 'reason' => 'Correo no coincide con la compra'];
    }

    if (!empty($payload['ord']) && !hash_equals((string)$ticket['flow_order'], (string)$payload['ord'])) {
        return ['valid' => false, 'reason' => 'Orden de venta no coincide'];
    }

    return [
        'valid' => true,
        'payload' => $payload,
        'ticket' => $ticket,
        'number' => $number,
    ];
}

function surteados_ticket_number_label(string $number): string
{
    $trimmed = trim($number);
    if (preg_match('/^\d+-\d{8}$/', $trimmed)) {
        return $trimmed;
    }

    $clean = preg_replace('/\D+/', '', $number);
    if ($clean === '') return '#' . $number;
    $clean = str_pad($clean, 6, '0', STR_PAD_LEFT);
    return '#' . substr($clean, 0, -3) . '.' . substr($clean, -3);
}
