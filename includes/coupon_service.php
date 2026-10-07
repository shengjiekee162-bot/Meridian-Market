<?php
declare(strict_types=1);

function coupon_by_code(string $code, bool $lock = false): ?array
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
        return null;
    }
    $sql = 'SELECT * FROM coupons WHERE code = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function assess_coupon(?array $coupon, int $subtotalCents): array
{
    if (!$coupon) {
        return ['ok' => false, 'message' => 'That coupon code was not found.', 'discount_cents' => 0];
    }
    $today = date('Y-m-d');
    if ($coupon['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This coupon is not active.', 'discount_cents' => 0];
    }
    if ($coupon['start_date'] > $today) {
        return ['ok' => false, 'message' => 'This coupon is not available yet.', 'discount_cents' => 0];
    }
    if ($coupon['expiry_date'] < $today) {
        return ['ok' => false, 'message' => 'This coupon has expired.', 'discount_cents' => 0];
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return ['ok' => false, 'message' => 'This coupon has reached its usage limit.', 'discount_cents' => 0];
    }
    if ($subtotalCents < to_cents($coupon['minimum_amount'])) {
        return ['ok' => false, 'message' => 'Add more to your cart to use this coupon. Minimum spend is ' . money($coupon['minimum_amount']) . '.', 'discount_cents' => 0];
    }

    if ($coupon['discount_type'] === 'percent') {
        $basisPoints = to_cents($coupon['discount_value']);
        $discount = (int) round($subtotalCents * $basisPoints / 10000);
    } else {
        $discount = to_cents($coupon['discount_value']);
    }
    if ($coupon['maximum_discount'] !== null && to_cents($coupon['maximum_discount']) > 0) {
        $discount = min($discount, to_cents($coupon['maximum_discount']));
    }
    $discount = max(0, min($discount, $subtotalCents));
    if ($discount <= 0) {
        return ['ok' => false, 'message' => 'This coupon does not change the current total.', 'discount_cents' => 0];
    }
    return ['ok' => true, 'message' => '', 'discount_cents' => $discount];
}

function shipping_cents_for(int $subtotalCents): int
{
    if ($subtotalCents <= 0) {
        return 0;
    }
    $threshold = to_cents(config('app', 'free_shipping_threshold', '150.00'));
    if ($subtotalCents >= $threshold) {
        return 0;
    }
    return to_cents(config('app', 'shipping_fee', '8.00'));
}

function calculate_totals(int $subtotalCents, ?array $coupon): array
{
    $assessment = $coupon ? assess_coupon($coupon, $subtotalCents) : ['ok' => false, 'message' => '', 'discount_cents' => 0];
    $discount = $assessment['ok'] ? (int) $assessment['discount_cents'] : 0;
    $shipping = shipping_cents_for($subtotalCents);
    $total = max(0, $subtotalCents - $discount + $shipping);
    return [
        'discount_cents' => $discount,
        'shipping_cents' => $shipping,
        'total_cents' => $total,
        'coupon_ok' => (bool) $assessment['ok'],
        'coupon_message' => (string) $assessment['message'],
    ];
}
