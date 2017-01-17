<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\Exception\PaginatedFetchingException;
use ApiConsumer\LinkProcessor\PreprocessedLink;

abstract class BasicPaginationFetcher extends AbstractFetcher
{
    /**
     * @var string
     */
    protected $paginationField;

    /**
     * @var int Number of items by page
     */
    protected $pageLength = 200;

    /**
     * @var array
     */
    protected $rawFeed = array();

    /**
     * Get pagination field
     *
     * @return string
     */
    protected function getPaginationField()
    {
        return $this->paginationField;
    }

    protected function getQuery($paginationId = null)
    {
        $query = parent::getQuery();

        if ($paginationId) {
            $paginationQuery = array($this->getPaginationField() => $paginationId);
            $query = array_merge($query, $paginationQuery);
        }

        return $query;
    }

    /**
     * @return array
     * @throws PaginatedFetchingException
     */
    protected function getLinksByPage()
    {
        $nextPaginationId = null;

        do {
            $url = $this->getUrl();
            $query = $this->getQuery($nextPaginationId);

            $response = $this->request($url, $query);

            $this->rawFeed = array_merge($this->rawFeed, $this->getItemsFromResponse($response));

            $nextPaginationId = $this->getPaginationIdFromResponse($response);

        } while (null !== $nextPaginationId);

        return $this->rawFeed;
    }

    protected function request($url, $query)
    {
        try {
            $response = $this->resourceOwner->request($url, $query, $this->token);
        } catch (\Exception $e) {
            //TODO: Set here "wait until API answers" logic (Twitter 429)
            throw new PaginatedFetchingException($this->rawFeed, $e);
        }

        return $response;
    }

    /**
     * { @inheritdoc }
     */
    public function fetchLinksFromUserFeed($token)
    {
        $this->setToken($token);
        $this->rawFeed = array();

        try {
            $rawFeed = $this->getLinksByPage();
        } catch (PaginatedFetchingException $e) {
            $newLinks = $this->parseLinks($e->getLinks());
            $e->setLinks($newLinks);
            throw $e;
        }

        $links = $this->parseLinks($rawFeed);

        return $links;
    }

    public function fetchAsClient($username)
    {
        try {
            $rawFeed = $this->getLinksByPage();
        } catch (PaginatedFetchingException $e) {
            $newLinks = $this->parseLinks($e->getLinks());
            $e->setLinks($newLinks);
            throw $e;
        }

        $links = $this->parseLinks($rawFeed);

        return $links;
    }

    abstract protected function getItemsFromResponse($response);

    /**
     * @param array $response
     * @return string|null
     */
    abstract protected function getPaginationIdFromResponse($response);

    /**
     * @param array $rawFeed
     * @return PreprocessedLink[]
     */
    abstract protected function parseLinks(array $rawFeed);

}
