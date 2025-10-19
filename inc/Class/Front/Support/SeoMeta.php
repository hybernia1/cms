<?php
declare(strict_types=1);

namespace Cms\Front\Support;

final class SeoMeta
{
    /**
     * @param array<string,string> $extra
     */
    public function __construct(
        private string $title,
        private ?string $description = null,
        private ?string $canonical = null,
        private array $extra = []
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
     * @return array{title:string,description:?string,canonical:?string,extra:array<string,string>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'extra' => $this->extra,
        ];
    }
}
