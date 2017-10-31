<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\InstagramUrlParser;
use Model\Link\Link;

class InstagramFollowsFetcher extends AbstractInstagramFetcher
{
    protected $url = 'users/self/follows';

    /**
     * { @inheritdoc }
     */
    protected function parseLinks(array $response = array())
    {
        $parsed = array();

        foreach ($response as $item) {
            if (isset($item['username'])) {
                $link = array();
                $link['url'] = 'https://www.instagram.com/' . $item['username'] . '/';

                $date = new \DateTime();
                $timestamp = ($date->getTimestamp()) * 1000;

                $preprocessedLink = new PreprocessedLink($link['url']);

                $link['timestamp'] = $timestamp;

                $preprocessedLink->setFirstLink(Link::buildFromArray($link));
                $preprocessedLink->setResourceItemId($item['id']);
                $preprocessedLink->setSource($this->resourceOwner->getName());
                $preprocessedLink->setType(InstagramUrlParser::INSTAGRAM_PROFILE);

                $parsed[] = $preprocessedLink;
            }
        }

        return $parsed;
    }
}
