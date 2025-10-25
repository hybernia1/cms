<?php
declare(strict_types=1);

namespace Cms\Front\Support;

final class SeoMeta
{
    /**
     * @param array<string,string|array<string,string>> $extra
     * @param list<array<string,mixed>> $structuredData
     */
    public function __construct(
        private string $title,
        private ?string $description = null,
        private ?string $canonical = null,
        private array $extra = [],
        private array $structuredData = []
    ) {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function canonical(): ?string
    {
        return $this->canonical;
    }

    /**
     * @return array<string,string>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function structuredData(): array
    {
        return $this->structuredData;
    }

    /**
     * @return array{title:string,description:?string,canonical:?string,extra:array<string,string|array<string,string>>,structured_data:list<array<string,mixed>>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'extra' => $this->extra,
            'structured_data' => $this->structuredData,
        ];
    }
}
