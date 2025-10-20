<?php
declare(strict_types=1);

namespace Cms\Admin\Http\Ajax\Settings;

use Cms\Admin\Http\AjaxResponse;
use Cms\Admin\Http\Ajax\Traits\ProvidesAjaxResponses;
use Cms\Admin\Settings\CmsSettings;
use Cms\Admin\Utils\DateTimeFactory;
use Cms\Admin\Utils\PermalinkSettings;
use Core\Database\Init as DB;

final class PermalinksSaveHandler
{
    use ProvidesAjaxResponses;
    use SettingsHelpers;

    public function __invoke(): AjaxResponse
    {
        $input = [
            'seo_urls'      => isset($_POST['seo_urls_enabled']) && (int)$_POST['seo_urls_enabled'] === 1,
            'post_base'     => isset($_POST['post_base']) ? (string)$_POST['post_base'] : '',
            'page_base'     => isset($_POST['page_base']) ? (string)$_POST['page_base'] : '',
            'tag_base'      => isset($_POST['tag_base']) ? (string)$_POST['tag_base'] : '',
            'category_base' => isset($_POST['category_base']) ? (string)$_POST['category_base'] : '',
        ];

        $permalinks = PermalinkSettings::normalize($input);

        $data = $this->readSettingsData();
        $data['permalinks'] = $permalinks;

        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($dataJson === false) {
            $dataJson = '{}';
        }

        DB::query()->table('settings')->update([
            'data'       => $dataJson,
            'updated_at' => DateTimeFactory::nowString(),
        ])->where('id', '=', 1)->execute();

        CmsSettings::refresh();

        return $this->successResponse('Trvalé odkazy byly uloženy.', [
            'redirect' => 'admin.php?r=settings&a=permalinks',
        ]);
    }
}
