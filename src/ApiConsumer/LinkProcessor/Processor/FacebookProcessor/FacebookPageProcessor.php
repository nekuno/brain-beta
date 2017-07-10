<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\Exception\UrlChangedException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
use Model\Link\Creator\CreatorFacebook;

class FacebookPageProcessor extends AbstractFacebookProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $id = $preprocessedLink->getResourceItemId() ?: $this->parser->getUsername($preprocessedLink->getUrl());
        $token = $preprocessedLink->getToken();

        $response = $this->resourceOwner->requestPage($id, $token);

        if ($this->isProfileResponse($response)) {
            $preprocessedLink->setType(FacebookUrlParser::FACEBOOK_PROFILE);
            throw new UrlChangedException($preprocessedLink->getUrl(), $preprocessedLink->getUrl(), 'Facebook page identified as profile');
        }

        return $response;
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $creator = CreatorFacebook::buildFromArray($link->toArray());
        $preprocessedLink->setFirstLink($creator);

        parent::hydrateLink($preprocessedLink, $data);
    }

    protected function isProfileResponse(array $response)
    {
        return isset($response['error']) && isset($response['error']['code']) && $response['error']['code'] == 803;
    }

}