<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link;
use Model\User\Token\Token;

class GoogleFetcher extends BasicPaginationFetcher
{
    protected $paginationField = 'pageToken';

    protected $pageLength = 20;

    protected $paginationId = null;

    /**
     * {@inheritDoc}
     */
    public function getUrl()
    {
        $googleId = $this->token->getResourceId();
        return 'plus/v1/people/' . $googleId . '/activities/public';
    }

    /**
     * {@inheritDoc}
     */
    protected function getQuery($paginationId = null)
    {
        return array_merge(
            parent::getQuery($paginationId),
            array(
                'maxResults' => $this->pageLength,
                'fields' => 'items(object(attachments(content,displayName,id,objectType,url)),title,published,updated),nextPageToken'
            )
        );
    }

    protected function getItemsFromResponse($response)
    {
        return isset($response['items']) && $response['items'] ? $response['items'] : array();
    }

    /**
     * {@inheritDoc}
     */
    protected function getPaginationIdFromResponse($response)
    {

        $paginationId = null;

        if (isset($response['nextPageToken'])) {
            $paginationId = $response['nextPageToken'];
        }

        if ($this->paginationId === $paginationId) {
            return null;
        }

        $this->paginationId = $paginationId;

        return $paginationId;
    }


    /**
     * {@inheritDoc}
     */
    protected function parseLinks(array $rawFeed)
    {
        $parsed = array();

        foreach ($rawFeed as $item) {
            if (!isset($item['object']['attachments'][0]['url'])) {
                continue;
            }

            $timestamp = null;
            if (array_key_exists('updated', $item)) {
                $date = new \DateTime($item['updated']);
                $timestamp = ($date->getTimestamp()) * 1000;
            } else if (array_key_exists('published', $item)) {
                $date = new \DateTime($item['published']);
                $timestamp = $date->getTimestamp() * 1000;
            }

            $item = $item['object']['attachments'][0];

            $link['url'] = $item['url'];
            $link['title'] = array_key_exists('displayName', $item) ? $item['displayName'] : null;
            $link['description'] = array_key_exists('content', $item) ? $item['content'] : null;
            $link['timestamp'] = $timestamp;

            $preprocessedLink = new PreprocessedLink($link['url']);
            $preprocessedLink->setFirstLink(Link::buildFromArray($link));
            $preprocessedLink->setResourceItemId(array_key_exists('id', $item) ? $item['id'] : null);
            $preprocessedLink->setSource($this->resourceOwner->getName());
            $parsed[] = $preprocessedLink;
        }

        return $parsed;
    }
}
