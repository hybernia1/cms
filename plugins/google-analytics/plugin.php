<?php
declare(strict_types=1);

use Core\Plugins\PluginRegistry;

cms_add_action('cms_register_plugins', static function (): void {
    $measurementId = cms_ga_measurement_id();

    PluginRegistry::register('google-analytics', [
        'name'        => 'Google Analytics',
        'description' => 'Vloží měřicí kód Google Analytics 4 do hlavičky webu.',
        'version'     => '1.0.0',
        'author'      => 'CMS Core',
        'homepage'    => 'https://analytics.google.com/',
        'meta'        => [
            'configured'      => $measurementId !== '',
            'measurement_id'  => $measurementId,
            'configuration_hint' => 'Nastavte proměnnou prostředí CMS_GA_MEASUREMENT_ID nebo konstantu CMS_GOOGLE_ANALYTICS_ID.'
        ],
    ]);
});

cms_add_action('cms_front_head', static function (): void {
    $measurementId = cms_ga_measurement_id();
    if ($measurementId === '') {
        return;
    }

    $measurementId = cms_apply_filters('cms_google_analytics_measurement_id', $measurementId);
    $measurementId = trim((string)$measurementId);
    if ($measurementId === '') {
        return;
    }

    $escaped = htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$escaped}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{$escaped}');
</script>
HTML;
});

if (!function_exists('cms_ga_measurement_id')) {
    function cms_ga_measurement_id(): string
    {
        $value = getenv('CMS_GA_MEASUREMENT_ID');
        if ($value === false || trim((string)$value) === '') {
            if (defined('CMS_GOOGLE_ANALYTICS_ID')) {
                $value = CMS_GOOGLE_ANALYTICS_ID;
            }
        }

        $value = is_string($value) ? trim($value) : '';
        return $value;
    }
}
