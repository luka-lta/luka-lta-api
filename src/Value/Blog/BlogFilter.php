<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Blog;

use LukaLtaApi\Value\AbstractDataTableFilterParameter;

class BlogFilter extends AbstractDataTableFilterParameter
{
    protected function getExtraFilterName(): array
    {
        return ['title'];
    }

    public function getTagId(): ?int
    {
        $params = $this->getQueryParameter();

        return isset($params['tag_id']) ? (int) $params['tag_id'] : null;
    }

    public function getSortColumn(): ?string
    {
        $params = $this->getQueryParameter();

        return $params['sortColumn'] ?? null;
    }

    public function getSortDirection(): ?string
    {
        $params = $this->getQueryParameter();
        $dir = strtoupper($params['sortDirection'] ?? '');

        return in_array($dir, ['ASC', 'DESC'], true) ? $dir : null;
    }

    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getPageSize();
    }
}
