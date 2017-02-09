<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
use Model\User\Token\TokensModel;

class FacebookProfileProcessor extends AbstractFacebookProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        if (!$preprocessedLink->getResourceItemId()) {
            throw new CannotProcessException($preprocessedLink->getUrl(), 'Cannot process as a facebook page because for lacking id');
        }

        $preprocessedLink->getFirstLink()->setProcessed(false);
        $id = $preprocessedLink->getResourceItemId();
        $token = $preprocessedLink->getToken();

        return $this->resourceOwner->requestProfile($id, $token);
    }
}