<?php
use Cms\Admin\Mail\MailTemplate;

/** @var array{
 *     resetUrl:string,
 *     siteTitle:string,
 *     userName?:string,
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Web';
}

$resetUrl = (string)($data['resetUrl'] ?? '');
$userName = trim((string)($data['userName'] ?? ''));

$greetingHtml = $userName !== ''
    ? 'Dobrý den ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ','
    : 'Dobrý den,';
$greetingText = $userName !== ''
    ? 'Dobrý den ' . $userName . ','
    : 'Dobrý den,';

$subject = sprintf('%s – obnova hesla', $siteTitle);

$html = <<<HTML
<p>{$greetingHtml}</p>
<p>pro reset hesla klikněte na odkaz: <a href="{$resetUrl}">{$resetUrl}</a></p>
<p>Odkaz platí 1 hodinu.</p>
<p>{$siteTitle}</p>
HTML;

$text = <<<TEXT
{$greetingText}

Pro reset hesla klikněte na odkaz: {$resetUrl}
Odkaz platí 1 hodinu.

{$siteTitle}
TEXT;

return new MailTemplate($subject, $html, $text);
