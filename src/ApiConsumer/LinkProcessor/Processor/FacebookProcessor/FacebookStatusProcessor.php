<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;

class FacebookStatusProcessor extends AbstractFacebookProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $id = $preprocessedLink->getResourceItemId();
        $token = $preprocessedLink->getToken();

        return $this->resourceOwner->requestStatus($id, $token);
    }
}