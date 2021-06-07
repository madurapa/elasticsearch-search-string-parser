<?php

namespace Spatie\ElasticsearchSearchStringParser\Directives;

use Spatie\ElasticsearchQueryBuilder\Builder\Aggregations\TermsAggregation;
use Spatie\ElasticsearchQueryBuilder\Builder\Aggregations\TopHitsAggregation;
use Spatie\ElasticsearchQueryBuilder\Builder;
use Spatie\ElasticsearchQueryBuilder\Builder\Sorts\Sort;
use Spatie\ElasticsearchSearchStringParser\SearchHit;

class ColumnGroupDirective extends GroupDirective
{
    public function __construct(protected array $groupableFields)
    {
    }

    public function canApply(string $pattern, array $values = []): bool
    {
        return in_array($values['value'], $this->groupableFields);
    }

    public function apply(Builder $builder, string $pattern, array $values = []): void
    {
        $field = $values[0];

        $aggregation = TermsAggregation::create('_grouping', "{$field}.keyword")
            ->aggregation(TopHitsAggregation::create('top_hit', 1));

        $builder->addAggregation($aggregation);
    }

    public function pattern(): string
    {
        return '/group:(?<value>.*?)(?:$|\s)/i';
    }

    public function transformToHits(array $results): array
    {
        return array_map(
            fn(array $bucket) => new SearchHit(
                $bucket['top_hit']['hits']['hits'][0]['_source'],
                $bucket
            ),
            $results['aggregations']['_grouping']['buckets']
        );
    }
}
