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
$userEmail = trim((string)($data['userEmail'] ?? ''));

$greetingHtml = $userName !== ''
    ? 'Dobrý den ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ','
    : 'Dobrý den,';
$greetingText = $userName !== ''
    ? 'Dobrý den ' . $userName . ','
    : 'Dobrý den,';

$subject = sprintf('%s – registrace přijata', $siteTitle);

$emailHtml = $userEmail !== '' ? htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') : 'váš e-mail';

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>potvrdili jsme přijetí vaší registrace. Administrátor nyní vaši žádost posoudí.</p>
<p>Jakmile účet aktivujeme, dáme vám vědět na adresu <strong>{$emailHtml}</strong>.</p>
<p>{$siteTitle}</p>
HTML;

$textEmail = $userEmail !== '' ? $userEmail : 'váš e-mail';

$text = <<<TEXT
{$greetingText}

Potvrdili jsme přijetí vaší registrace. Administrátor nyní vaši žádost posoudí.
Jakmile účet aktivujeme, dáme vám vědět na adresu {$textEmail}.

{$siteTitle}
TEXT;

return new MailTemplate($subject, $html, $text);
