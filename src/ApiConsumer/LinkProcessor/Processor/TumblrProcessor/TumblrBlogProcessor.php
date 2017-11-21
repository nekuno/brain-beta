<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Creator;

class TumblrBlogProcessor extends AbstractTumblrProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $token = $preprocessedLink->getToken();

        $response = $this->resourceOwner->requestBlog($preprocessedLink->getResourceItemId(), $token);

        return isset($response['response']['blog']) ? $response['response']['blog'] : null;
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $creator = Creator::buildFromArray($link->toArray());
        $preprocessedLink->setFirstLink($creator);
    }
}