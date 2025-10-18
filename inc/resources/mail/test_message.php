<?php
use Cms\Mail\MailTemplate;

/** @var array{
 *     siteTitle:string,
 * } $data
 */

$siteTitle = trim((string)($data['siteTitle'] ?? ''));
if ($siteTitle === '') {
    $siteTitle = 'Redakční systém';
}

$subject = 'Testovací e-mail';

$html = <<<HTML
<p>Toto je testovací e-mail z redakčního systému {$siteTitle}.</p>
HTML;

$text = <<<TEXT
Toto je testovací e-mail z redakčního systému {$siteTitle}.
TEXT;

return new MailTemplate($subject, $html, $text);
