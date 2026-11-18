<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/PaymentGateway.php';

use KodVadAll\Core\PaymentGateway;

$method = new ReflectionMethod(PaymentGateway::class, 'amountMatches');
$method->setAccessible(true);

$cases = [
    ['exact whole amount', 100.00, 10000, true],
    ['exact decimal amount', 149.99, 14999, true],
    ['rounding to minor units', 0.10, 10, true],
    ['reject overpayment', 100.00, 10001, false],
    ['reject underpayment', 100.00, 9999, false],
    ['reject zero payment', 100.00, 0, false],
];

foreach ($cases as [$label, $expected, $received, $wanted]) {
    $actual = $method->invoke(null, $expected, $received);
    if ($actual !== $wanted) {
        fwrite(STDERR, "Payment callback amount test failed: {$label}.\n");
        exit(1);
    }
}

echo "KodVadAll payment callback amount tests passed.\n";
