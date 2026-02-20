<?php

declare(strict_types=1);

namespace LukaLtaApi\QueryBuilder;

use LukaLtaApi\QueryBuilder\Builder\DefaultQueryBuilder;
use LukaLtaApi\QueryBuilder\Builder\EntryExitPageQueryBuilder;
use LukaLtaApi\QueryBuilder\Builder\EventNameQueryBuilder;
use LukaLtaApi\QueryBuilder\Builder\PageTitleQueryBuilder;
use LukaLtaApi\QueryBuilder\Builder\PathNameQueryBuilder;
use LukaLtaApi\Value\Tracking\MetricParameter;

class QueryBuilderFactory
{
    /** @var MetricQueryBuilderInterface[] */
    private array $builders;

    public function __construct(
        EventNameQueryBuilder $eventNameBuilder,
        PageTitleQueryBuilder $pageTitleBuilder,
        PathNameQueryBuilder $pathNameBuilder,
        EntryExitPageQueryBuilder $entryExitBuilder,
        DefaultQueryBuilder $defaultBuilder
    ) {
        $this->builders = [
            $eventNameBuilder,
            $pageTitleBuilder,
            $pathNameBuilder,
            $entryExitBuilder,
            $defaultBuilder, // Fallback immer als letztes
        ];
    }

    public function create(MetricParameter $parameter): MetricQueryBuilderInterface
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($parameter)) {
                return $builder;
            }
        }

        throw new \RuntimeException("No builder found for parameter: {$parameter->value}");
    }
}
