<?php
declare(strict_types=1);

use Cms\Mail\MailTemplate;

/**
 * @var array{
 *     siteTitle?:string,
 *     userName?:string,
 *     userEmail?:string,
 *     loginUrl?:string,
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Web';
}

$userName = trim((string)($data['userName'] ?? ''));
$userEmail = trim((string)($data['userEmail'] ?? ''));
$loginUrl = trim((string)($data['loginUrl'] ?? ''));

$greetingHtml = $userName !== ''
    ? 'Dobrý den ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ','
    : 'Dobrý den,';
$greetingText = $userName !== ''
    ? 'Dobrý den ' . $userName . ','
    : 'Dobrý den,';

$linkHtml = '';
$linkText = '';
if ($loginUrl !== '') {
    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $linkHtml = '<a href="' . $safeUrl . '">' . $safeUrl . '</a>';
    $linkText = $loginUrl;
}

$subject = sprintf('%s – registrace dokončena', $siteTitle);

$emailHtml = $userEmail !== '' ? htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') : 'váš e-mail';
$emailText = $userEmail !== '' ? $userEmail : 'váš e-mail';

$loginSentenceHtml = $linkHtml !== ''
    ? 'Přihlaste se na ' . $linkHtml . ' pomocí e-mailu <strong>' . $emailHtml . '</strong> a hesla, které jste si zvolili při registraci.'
    : 'Přihlaste se pomocí e-mailu <strong>' . $emailHtml . '</strong> a hesla, které jste si zvolili při registraci.';

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>vítáme vás v redakčním systému <strong>{$siteTitle}</strong>. Váš účet je nyní aktivní a můžete se přihlásit.</p>
<p>{$loginSentenceHtml}</p>
<p>Přejeme příjemnou práci!</p>
<p>{$siteTitle}</p>
HTML;

$textLines = [
    $greetingText,
    '',
    "Vítáme vás v redakčním systému {$siteTitle}. Váš účet je nyní aktivní a můžete se přihlásit.",
];

if ($linkText !== '') {
    $textLines[] = "Přihlaste se na {$linkText} pomocí e-mailu {$emailText} a hesla, které jste si zvolili při registraci.";
} else {
    $textLines[] = "Přihlaste se pomocí e-mailu {$emailText} a hesla, které jste si zvolili při registraci.";
}

$textLines[] = '';
$textLines[] = 'Přejeme příjemnou práci!';
$textLines[] = '';
$textLines[] = $siteTitle;

$text = implode("\n", $textLines);

return new MailTemplate($subject, $html, $text);
