<?php
declare(strict_types=1);

use Cms\Admin\Mail\MailTemplate;

/**
 * @var array{
 *     siteTitle?:string,
 *     userName?:string,
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

$subject = sprintf('%s – účet byl odstraněn', $siteTitle);

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>váš účet v systému <strong>{$siteTitle}</strong> byl odstraněn.</p>
<p>Pokud se domníváte, že jde o chybu, kontaktujte prosím administrátora.</p>
<p>{$siteTitle}</p>
HTML;

$text = <<<TEXT
{$greetingText}

Váš účet v systému {$siteTitle} byl odstraněn.
Pokud se domníváte, že jde o chybu, kontaktujte prosím administrátora.

{$siteTitle}
TEXT;

return new MailTemplate($subject, $html, $text);
