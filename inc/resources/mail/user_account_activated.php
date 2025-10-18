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

$loginSentenceHtml = '';
$loginSentenceText = '';

if ($loginUrl !== '') {
    $safeUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $loginSentenceHtml = 'Můžete se přihlásit na ' . '<a href="' . $safeUrl . '">' . $safeUrl . '</a>' . ' pomocí e-mailu <strong>' . htmlspecialchars($userEmail !== '' ? $userEmail : 'váš e-mail', ENT_QUOTES, 'UTF-8') . '</strong>.';
    $loginSentenceText = 'Můžete se přihlásit na ' . $loginUrl . ' pomocí e-mailu ' . ($userEmail !== '' ? $userEmail : 'váš e-mail') . '.';
}

$subject = sprintf('%s – účet byl aktivován', $siteTitle);

$loginParagraphHtml = $loginSentenceHtml !== '' ? '<p>' . $loginSentenceHtml . '</p>' : '';

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>váš účet v systému <strong>{$siteTitle}</strong> byl aktivován a je opět připraven k použití.</p>
{$loginParagraphHtml}
<p>{$siteTitle}</p>
HTML;

$textLines = [
    $greetingText,
    '',
    "Váš účet v systému {$siteTitle} byl aktivován a je opět připraven k použití.",
];
if ($loginSentenceText !== '') {
    $textLines[] = $loginSentenceText;
}
$textLines[] = '';
$textLines[] = $siteTitle;

$text = implode("\n", $textLines);

return new MailTemplate($subject, $html, $text);
