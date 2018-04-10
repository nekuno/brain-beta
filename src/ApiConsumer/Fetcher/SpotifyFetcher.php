<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Link;
use Model\Token\Token;

abstract class SpotifyFetcher extends BasicPaginationFetcher
{
    //max limits allowed by Spotify API to reduce calls
    const MAX_PLAYLISTS_PER_USER = 50;
    const MAX_TRACKS_PER_PLAYLIST = 100;

    /**
     * @var array
     */
    protected $rawFeed = array();

    /**
     * @var array
     */
    protected $query = array();

    protected $paginationField = 'offset';

    /**
     * { @inheritdoc }
     */
    protected function parseLinks(array $response = array())
    {
        $parsed = array();

        foreach ($response as $item) {
            if (isset($item['track']) && null !== $item['track']['id']) {
                $link = new Link();
                $link->setUrl($item['track']['external_urls']['spotify']);
                $link->setTitle($item['track']['name']);

                $artistList = array();
                foreach ($item['track']['artists'] as $artist) {
                    $artistList[] = $artist['name'];
                }

                $timestamp = null;
                if (array_key_exists('added_at', $item)) {
                    $date = new \DateTime($item['added_at']);
                    $timestamp = ($date->getTimestamp()) * 1000;
                }

                $preprocessedLink = new PreprocessedLink($link->getUrl());

                $link->setDescription($item['track']['album']['name'] . ' : ' . implode(', ', $artistList));
                $link->setCreated($timestamp);

                $preprocessedLink->setFirstLink($link);
                $preprocessedLink->setResourceItemId($item['track']['id']);
                $preprocessedLink->setSource($this->resourceOwner->getName());
                $preprocessedLink->setToken($this->getToken());

                $parsed[] = $preprocessedLink;
            }
        }

        return $parsed;
    }

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
        return $response['items'] ?: array();
    }

    protected function getPaginationIdFromResponse($response)
    {
        if ($response['next']) {
            $startPos = strpos($response['next'], 'offset=') + 7;
            $endPos = strpos($response['next'], '&');
            $length = $endPos - $startPos;

            return substr($response['next'], $startPos, $length);
        } else {
            return null;
        }
    }
}
