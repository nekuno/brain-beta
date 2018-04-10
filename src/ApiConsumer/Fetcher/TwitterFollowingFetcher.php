<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Creator;

class TwitterFollowingFetcher extends AbstractTwitterFetcher
{
    protected $url = 'friends/ids.json';

    protected $paginationField = 'cursor';

    protected $pageLength = 5000;

    protected function getQuery($paginationId = null)
    {
        return array_merge(
            parent::getQuery($paginationId),
            array(
                'count' => $this->pageLength,
            )
        );
    }

    protected function getItemsFromResponse($response)
    {
        return isset($response['ids']) ? $response['ids'] : array();

    }

    protected function getPaginationIdFromResponse($response)
    {
        $paginationId = isset($response['next_cursor']) ? $response['next_cursor'] : null;
        if ($paginationId == 0) {
            return null;
        }

        return $paginationId;
    }

    /**
     * @inheritdoc
     */
    protected function parseLinks(array $rawFeed)
    {
        $preprocessedLinks = array();
        foreach ($rawFeed as $id) {
            $link = new Creator();
            $link->setUrl('https://twitter.com/intent/user?user_id=' . $id);
            $link->setCreated(1000 * time());

            $preprocessedLink = new PreprocessedLink($link->getUrl());
            $preprocessedLink->setFirstLink($link);
            $preprocessedLink->setSource($this->resourceOwner->getName());
            $preprocessedLink->setResourceItemId($id);
            $preprocessedLinks[] = $preprocessedLink;
        }

        return $preprocessedLinks;
    }

}