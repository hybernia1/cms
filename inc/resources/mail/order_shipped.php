<?php
declare(strict_types=1);

use Cms\Admin\Mail\MailTemplate;
use Cms\Models\Customer;
use Cms\Models\Order;
use Cms\Models\OrderShipment;

/**
 * @var Order $order
 * @var Customer $customer
 * @var OrderShipment $shipment
 */

$orderNumber = isset($order->order_number) ? (string)$order->order_number : '';
$subject = $orderNumber !== ''
    ? sprintf('Objednávka %s byla odeslána', $orderNumber)
    : 'Vaše objednávka byla odeslána';

$carrier = isset($shipment->carrier) ? (string)$shipment->carrier : '';
$tracking = isset($shipment->tracking_number) ? (string)$shipment->tracking_number : '';
$shippedAt = isset($shipment->shipped_at) ? (string)$shipment->shipped_at : '';

$shippedDate = '';
if ($shippedAt !== '') {
    try {
        $dt = new \DateTimeImmutable($shippedAt);
        $shippedDate = $dt->format('d.m.Y H:i');
    } catch (\Throwable) {
        $shippedDate = $shippedAt;
    }
}

$orderNumberHtml = htmlspecialchars($orderNumber !== '' ? $orderNumber : 'bez čísla', ENT_QUOTES, 'UTF-8');
$html = '<p>Dobrý den,</p>'
    . '<p>vaše objednávka <strong>' . $orderNumberHtml . '</strong> byla odeslána.</p>';

if ($carrier !== '') {
    $html .= '<p>Dopravce: ' . htmlspecialchars($carrier, ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($tracking !== '') {
    $html .= '<p>Číslo zásilky: ' . htmlspecialchars($tracking, ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($shippedDate !== '') {
    $html .= '<p>Datum odeslání: ' . htmlspecialchars($shippedDate, ENT_QUOTES, 'UTF-8') . '</p>';
}

$html .= '<p>Děkujeme, že jste nakoupili u nás.</p>';

$text = "Dobrý den,\n";
$text .= 'vaše objednávka ' . ($orderNumber !== '' ? $orderNumber : 'bez čísla') . ' byla odeslána.' . "\n";
if ($carrier !== '') {
    $text .= 'Dopravce: ' . $carrier . "\n";
}
if ($tracking !== '') {
    $text .= 'Číslo zásilky: ' . $tracking . "\n";
}
if ($shippedDate !== '') {
    $text .= 'Datum odeslání: ' . $shippedDate . "\n";
}
$text .= "\nDěkujeme, že jste nakoupili u nás.";

return new MailTemplate($subject, $html, $text);
