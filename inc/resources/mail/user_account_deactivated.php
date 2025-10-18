<?php
declare(strict_types=1);

use Cms\Mail\MailTemplate;

/**
 * @var array{
 *     siteTitle?:string,
 *     userName?:string,
 *     userEmail?:string,
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Web';
}

$userName = trim((string)($data['userName'] ?? ''));

$greetingHtml = $userName !== ''
    ? 'Dobrý den ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ','
    : 'Dobrý den,';
$greetingText = $userName !== ''
    ? 'Dobrý den ' . $userName . ','
    : 'Dobrý den,';

$subject = sprintf('%s – účet byl dočasně deaktivován', $siteTitle);

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>váš účet v systému <strong>{$siteTitle}</strong> byl dočasně deaktivován. Do opětovné aktivace se nebudete moci přihlásit.</p>
<p>Pokud máte dotazy, odpovězte na tento e-mail.</p>
<p>{$siteTitle}</p>
HTML;

$text = <<<TEXT
{$greetingText}

Váš účet v systému {$siteTitle} byl dočasně deaktivován. Do opětovné aktivace se nebudete moci přihlásit.
Pokud máte dotazy, odpovězte na tento e-mail.

{$siteTitle}
TEXT;

return new MailTemplate($subject, $html, $text);
