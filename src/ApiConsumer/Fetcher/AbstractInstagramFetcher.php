<?php

namespace ApiConsumer\Fetcher;

abstract class AbstractInstagramFetcher extends BasicPaginationFetcher
{
    protected $paginationField = 'next_max_id';
    protected $query = array();

    public function getQuery($paginationId = null)
    {
        $parentQuery = parent::getQuery($paginationId);
        return array_merge($parentQuery, $this->query);
    }

    /**
     * @param array $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    protected function getItemsFromResponse($response)
    {
        return isset($response['data']) ? $response['data'] : array();
    }

    protected function getPaginationIdFromResponse($response)
    {
        if (isset($response['pagination']['next_url'])) {
            $startPos = strpos($response['pagination']['next_url'], 'max_id=') + 7;
            $length = strlen($response['pagination']['next_url']) - $startPos;
            return substr($response['pagination']['next'], $startPos, $length);
        } else {
            return null;
        }
    }
}
