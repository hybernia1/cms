<?php
declare(strict_types=1);

use Cms\Admin\Mail\MailTemplate;
use Cms\Models\Customer;
use Cms\Models\Order;

/**
 * @var Order $order
 * @var Customer $customer
 * @var string $fromStatus
 * @var string $toStatus
 * @var string|null $note
 */

$statusLabels = [
    'new' => 'Nová',
    'awaiting_payment' => 'Čeká na platbu',
    'packed' => 'Zabaleno',
    'shipped' => 'Odeslána',
    'delivered' => 'Doručena',
    'cancelled' => 'Zrušena',
];

$orderNumber = isset($order->order_number) ? (string)$order->order_number : '';
$fromLabel = $statusLabels[$fromStatus] ?? ucwords(str_replace('_', ' ', $fromStatus));
$toLabel = $statusLabels[$toStatus] ?? ucwords(str_replace('_', ' ', $toStatus));

$subject = trim($orderNumber) !== ''
    ? sprintf('Aktualizace objednávky %s', $orderNumber)
    : 'Aktualizace objednávky';

$orderNumberHtml = htmlspecialchars($orderNumber !== '' ? $orderNumber : 'bez čísla', ENT_QUOTES, 'UTF-8');
$toLabelHtml = htmlspecialchars($toLabel, ENT_QUOTES, 'UTF-8');

$html = '<p>Dobrý den,</p>'
    . '<p>stav vaší objednávky <strong>' . $orderNumberHtml . '</strong> byl změněn na <strong>' . $toLabelHtml . '</strong>.</p>';

if ($fromLabel !== $toLabel) {
    $fromLabelHtml = htmlspecialchars($fromLabel, ENT_QUOTES, 'UTF-8');
    $html .= '<p>Předchozí stav: ' . $fromLabelHtml . '.</p>';
}

if ($note !== null && trim($note) !== '') {
    $html .= '<p><strong>Poznámka:</strong><br>'
        . nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8'), false)
        . '</p>';
}

$html .= '<p>Děkujeme za váš nákup.</p>';

$text = "Dobrý den,\n";
$text .= 'stav vaší objednávky ' . ($orderNumber !== '' ? $orderNumber : 'bez čísla') . ' byl změněn na ' . $toLabel . ".\n";
if ($fromLabel !== $toLabel) {
    $text .= 'Předchozí stav: ' . $fromLabel . ".\n";
}
if ($note !== null && trim($note) !== '') {
    $text .= "\nPoznámka:\n" . trim($note) . "\n";
}
$text .= "\nDěkujeme za váš nákup.";

return new MailTemplate($subject, $html, $text);
