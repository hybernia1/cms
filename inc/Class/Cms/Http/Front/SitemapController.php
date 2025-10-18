<?php
declare(strict_types=1);

namespace Cms\Http\Front;

final class SitemapController extends BaseFrontController
{
    public function index(): void
    {
        $xml = $this->services->sitemaps()->renderIndex();
        $this->respondWithXml($xml);
    }

    public function section(string $key): void
    {
        $xml = $this->services->sitemaps()->renderSection($key);
        if ($xml === null) {
            $this->renderNotFound();
            return;
        }

        $this->respondWithXml($xml);
    }

    private function respondWithXml(string $xml): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
    }
}
