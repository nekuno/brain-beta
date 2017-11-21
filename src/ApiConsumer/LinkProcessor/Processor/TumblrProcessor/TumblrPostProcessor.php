<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;

class TumblrPostProcessor extends AbstractTumblrProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $token = $preprocessedLink->getToken();
        $firstLink = $preprocessedLink->getFirstLink();
        $postId = $firstLink->getId();

        $response = $this->resourceOwner->requestPost($preprocessedLink->getResourceItemId(), $postId, $token);

        return isset($response['response']['posts'][0]) ? $response['response']['posts'][0] : null;
    }

    public function addTags(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::addTags($preprocessedLink, $data);

        $link = $preprocessedLink->getFirstLink();

        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                $link->addTag($this->buildTag($tag));
            }
        }
    }

    protected function buildTag($tagName)
    {
        $tag = array();
        $tag['name'] = $tagName;

        return $tag;
    }
}