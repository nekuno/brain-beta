<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Link;

class InstagramLikedFetcher extends AbstractInstagramFetcher
{
    protected $url = 'users/self/media/liked';

    /**
     * { @inheritdoc }
     */
    protected function parseLinks(array $response = array())
    {
        $parsed = array();

        foreach ($response as $item) {
            if (isset($item['link'])) {
                $link = array();
                $link['url'] = $item['link'];

                $timestamp = null;
                if (array_key_exists('created_time', $item)) {
                    $date = new \DateTime($item['created_time']);
                    $timestamp = ($date->getTimestamp()) * 1000;
                }

                $preprocessedLink = new PreprocessedLink($link['url']);

                $link['timestamp'] = $timestamp;

                $preprocessedLink->setFirstLink(Link::buildFromArray($link));
                $preprocessedLink->setResourceItemId($item['id']);
                $preprocessedLink->setSource($this->resourceOwner->getName());
                $preprocessedLink->setType($item['type'] === 'image' ? InstagramUrlParser::INSTAGRAM_IMAGE : InstagramUrlParser::INSTAGRAM_VIDEO);

                $parsed[] = $preprocessedLink;
            }
        }

        return $parsed;
    }
}
