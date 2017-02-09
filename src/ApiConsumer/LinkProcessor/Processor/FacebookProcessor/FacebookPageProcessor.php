<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Creator;

class FacebookPageProcessor extends AbstractFacebookProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $id = $preprocessedLink->getResourceItemId() ?: $this->parser->getUsername($preprocessedLink->getUrl());
        $token = $preprocessedLink->getToken();

        return $this->resourceOwner->requestPage($id, $token);
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $creator = Creator::buildFromArray($link->toArray());
        $preprocessedLink->setFirstLink($creator);

        parent::hydrateLink($preprocessedLink, $data);
    }

}