<?php

namespace Spatie\ElasticSearchQueryBuilder\Builder;

use Elasticsearch\Client;
use Spatie\ElasticSearchQueryBuilder\Builder\Aggregations\Aggregation;
use Spatie\ElasticSearchQueryBuilder\Builder\Queries\BoolQuery;
use Spatie\ElasticSearchQueryBuilder\Builder\Queries\Query;
use Spatie\ElasticSearchQueryBuilder\Builder\Sorts\Sort;

class Builder
{
    protected ?BoolQuery $query = null;

    protected ?AggregationCollection $aggregations = null;

    protected ?SortCollection $sorts = null;

    protected ?string $searchIndex = null;

    protected ?int $size = null;

    protected ?int $from = null;

    public function __construct(protected Client $client)
    {
    }

    public function addQuery(Query $query, string $boolType = 'must'): static
    {
        if (! $this->query) {
            $this->query = new BoolQuery();
        }

        $this->query->add($query, $boolType);

        return $this;
    }

    public function addAggregation(Aggregation $aggregation): static
    {
        if (! $this->aggregations) {
            $this->aggregations = new AggregationCollection();
        }

        $this->aggregations->add($aggregation);

        return $this;
    }

    public function addSort(Sort $sort): static
    {
        if (! $this->sorts) {
            $this->sorts = new SortCollection();
        }

        $this->sorts->add($sort);

        return $this;
    }

    public function search(): array
    {
        $payload = $this->getPayload();

        $params = [
            'body' => $payload,
        ];

        if ($this->searchIndex) {
            $params['index'] = $this->searchIndex;
        }

        if ($this->size !== null) {
            $params['size'] = $this->size;
        }

        if ($this->from !== null) {
            $params['from'] = $this->from;
        }

        return $this->client->search($params);
    }

    public function index(string $searchIndex): static
    {
        $this->searchIndex = $searchIndex;

        return $this;
    }

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function from(int $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function getPayload(): array
    {
        $payload = [];

        if ($this->query) {
            $payload['query'] = $this->query->toArray();
        }

        if ($this->aggregations) {
            $payload['aggs'] = $this->aggregations->toArray();
        }

        if ($this->sorts) {
            $payload['sorts'] = $this->sorts->toArray();
        }

        return $payload;
    }
}
