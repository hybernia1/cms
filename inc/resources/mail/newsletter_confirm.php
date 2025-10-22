<?php
declare(strict_types=1);

use Cms\Admin\Mail\MailTemplate;

/**
 * @var array{
 *     siteTitle?:string,
 *     subscriberEmail?:string,
 *     confirmUrl?:string,
 *     unsubscribeUrl?:string|null
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Web';
}

$email = trim((string)($data['subscriberEmail'] ?? ''));
$confirmUrl = trim((string)($data['confirmUrl'] ?? ''));
$unsubscribeUrl = trim((string)($data['unsubscribeUrl'] ?? ''));

$subject = sprintf('%s – potvrzení odběru newsletteru', $siteTitle);

$safeConfirmUrl = $confirmUrl !== '' ? htmlspecialchars($confirmUrl, ENT_QUOTES, 'UTF-8') : '';
$safeUnsubscribeUrl = $unsubscribeUrl !== '' ? htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') : '';

$confirmLinkHtml = $confirmUrl !== ''
    ? '<a href="' . $safeConfirmUrl . '">Potvrdit odběr</a>'
    : $safeConfirmUrl;

$unsubscribeHtml = '';
if ($unsubscribeUrl !== '') {
    $unsubscribeHtml = '<p>Pokud jste se k odběru nepřihlásili vy, můžete se odhlásit zde: '
        . '<a href="' . $safeUnsubscribeUrl . '">' . $safeUnsubscribeUrl . '</a>.</p>';
}

$greetingHtml = $email !== ''
    ? 'Dobrý den, potvrďte prosím odběr pro e-mail <strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong>.'
    : 'Dobrý den, potvrďte prosím svůj odběr newsletteru.';

$confirmHtml = $confirmUrl !== ''
    ? '<p>Potvrďte prosím svůj odběr kliknutím na následující tlačítko:</p><p>' . $confirmLinkHtml . '</p>'
    : '';

$confirmFallbackHtml = $confirmUrl !== ''
    ? '<p>Pokud tlačítko nefunguje, zkopírujte a vložte tuto adresu do prohlížeče:</p>'
        . '<p><a href="' . $safeConfirmUrl . '">' . $safeConfirmUrl . '</a></p>'
    : '';

$html = <<<HTML
<p>{$greetingHtml}</p>
{$confirmHtml}
{$confirmFallbackHtml}
{$unsubscribeHtml}
<p>Děkujeme,<br>{$siteTitle}</p>
HTML;

$textLines = [];
$textLines[] = $email !== ''
    ? "Dobrý den, potvrďte prosím odběr pro e-mail {$email}."
    : 'Dobrý den, potvrďte prosím svůj odběr newsletteru.';

if ($confirmUrl !== '') {
    $textLines[] = '';
    $textLines[] = 'Potvrďte svůj odběr otevřením tohoto odkazu:';
    $textLines[] = $confirmUrl;
}

if ($unsubscribeUrl !== '') {
    $textLines[] = '';
    $textLines[] = 'Pokud jste se k odběru nepřihlásili vy, můžete se odhlásit zde:';
    $textLines[] = $unsubscribeUrl;
}

$textLines[] = '';
$textLines[] = 'Děkujeme,';
$textLines[] = $siteTitle;

$text = implode("\n", $textLines);

return new MailTemplate($subject, $html, $text);
