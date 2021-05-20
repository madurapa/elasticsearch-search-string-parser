<?php

namespace Spatie\ElasticSearchQueryBuilder\Tests\stubs;

use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\FilterAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\MaxAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\MinAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\NestedAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\ReverseNestedAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\TermsAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\TopHitsAggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Builder;
use Spatie\ElasticSearchQueryBuilder\Builder\Queries\TermQuery;
use Spatie\ElasticSearchQueryBuilder\Builder\Sorts\Sort;
use Spatie\ElasticSearchQueryBuilder\Directives\GroupDirective;
use Spatie\ElasticSearchQueryBuilder\SearchHit;

class FlareContextGroupDirective extends GroupDirective
{
    protected array $allowedValues = [
        'user.id' => ['user', 'user.id'],
        'request.useragent' => ['useragent', 'request.useragent'],
        'env.laravel_version' => ['laravel_version', 'env.laravel_version'],
        'env.php_version' => ['php_version', 'env.php_version'],
    ];

    const GROUPING_AGGREGATION = '_grouping';

    public function canApply(string $pattern, array $values = []): bool
    {
        $field = $this->getFieldForValue($values['value']);

        return $field !== null;
    }

    public function pattern(): string
    {
        return '/group:(?<value>.*?)(?:$|\s)/i';
    }

    public function apply(Builder $builder, string $pattern, array $values = []): void
    {
        $field = $this->getFieldForValue($values['value']);

        $termsAggregation = TermsAggregation::create('terms', 'context_items.value.keyword')
            ->missing(0)
            ->order([
                'occurrence>last_received_at' => 'desc',
            ])
            ->aggregation(
                ReverseNestedAggregation::create('occurrence')
                    ->aggregation(TopHitsAggregation::create('recent_error_occurrence', 1, Sort::create('received_at', Sort::DESC)))
                    ->aggregation(MaxAggregation::create('last_received_at', 'received_at'))
                    ->aggregation(MinAggregation::create('first_received_at', 'received_at'))
            );

        $filterAggregation = FilterAggregation::create('filter', TermQuery::create('context_items.key', $field))
            ->aggregation($termsAggregation);

        $builder->addAggregation(
            NestedAggregation::create(self::GROUPING_AGGREGATION, 'context_items')->aggregation($filterAggregation)
        );
    }

    public function transformToHits(array $results): array
    {
        return array_map(
            fn(array $bucket) => new SearchHit(
                $bucket['occurrence']['recent_error_occurrence']['hits']['hits'][0]['_source'],
                $bucket['occurrence']
            ),
            $results['aggregations'][self::GROUPING_AGGREGATION]['filter']['terms']['buckets']
        );
    }

    protected function getFieldForValue($value): ?string
    {
        $allowed = array_filter(
            $this->allowedValues,
            fn(array $allowedValues, string $field) => in_array($value, $allowedValues),
            ARRAY_FILTER_USE_BOTH
        );

        return array_key_first($allowed);
    }
}

