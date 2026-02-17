<?php

namespace LukaLtaApi\QueryBuilder;

use LukaLtaApi\QueryBuilder\Value\QueryContext;
use LukaLtaApi\Value\Tracking\MetricParameter;

interface MetricQueryBuilderInterface
{
    public function build(QueryContext $context): string;
    public function supports(MetricParameter $parameter): bool;
}
