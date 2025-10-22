<?php
declare(strict_types=1);

use Cms\Admin\Mail\MailTemplate;

/**
 * @var array{
 *     siteTitle?:string,
 *     subscriberEmail?:string,
 *     subscribeUrl?:string|null
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Web';
}

$email = trim((string)($data['subscriberEmail'] ?? ''));
$subscribeUrl = trim((string)($data['subscribeUrl'] ?? ''));

$subject = sprintf('%s – odhlášení z newsletteru potvrzeno', $siteTitle);

$greetingHtml = $email !== ''
    ? 'Dobrý den, e-mail <strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong> byl úspěšně odhlášen z odběru.'
    : 'Dobrý den, Váš e-mail byl úspěšně odhlášen z odběru newsletteru.';

$subscribeHtml = '';
if ($subscribeUrl !== '') {
    $safeSubscribeUrl = htmlspecialchars($subscribeUrl, ENT_QUOTES, 'UTF-8');
    $subscribeHtml = '<p>Rozmysleli jste si to? K odběru se můžete kdykoli znovu přihlásit na <a href="'
        . $safeSubscribeUrl . '">' . $safeSubscribeUrl . '</a>.</p>';
}

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>Je nám líto, že odcházíte. Pokud jste odhlášení nespustili vy, můžete se kdykoli ozvat na podporu.</p>
{$subscribeHtml}
<p>S pozdravem,<br>{$siteTitle}</p>
HTML;

$textLines = [];
$textLines[] = $email !== ''
    ? "Dobrý den, e-mail {$email} byl úspěšně odhlášen z odběru."
    : 'Dobrý den, Váš e-mail byl úspěšně odhlášen z odběru newsletteru.';
$textLines[] = '';
$textLines[] = 'Je nám líto, že odcházíte. Pokud jste odhlášení nespustili vy, kontaktujte prosím podporu.';

if ($subscribeUrl !== '') {
    $textLines[] = '';
    $textLines[] = 'K odběru se můžete kdykoli znovu přihlásit zde:';
    $textLines[] = $subscribeUrl;
}

$textLines[] = '';
$textLines[] = 'S pozdravem,';
$textLines[] = $siteTitle;

$text = implode("\n", $textLines);

return new MailTemplate($subject, $html, $text);
