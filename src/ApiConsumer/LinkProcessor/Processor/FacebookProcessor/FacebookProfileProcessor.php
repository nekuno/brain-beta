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
        //TODO: When Facebook App Token is implemented, include option to request public if source != facebook
        if (!($preprocessedLink->getSource() == TokensModel::FACEBOOK && $preprocessedLink->getResourceItemId())) {
            throw new CannotProcessException($preprocessedLink->getUrl(), 'Cannot process as a facebook page because for lacking token or id');
        }

        $id = $preprocessedLink->getResourceItemId();
        $token = $preprocessedLink->getToken();

        if ($preprocessedLink->getType() === FacebookUrlParser::FACEBOOK_PAGE) {
            return $this->resourceOwner->requestPage($id, $token);
        }

        if ($this->parser->isStatusId($id)) {
            return $this->resourceOwner->requestStatus($id, $token);
        }

        return $this->resourceOwner->requestProfile($id, $token);
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $link->setDescription(isset($data['description']) ? $data['description'] : $this->buildDescriptionFromTitle($data));
        $link->setTitle(isset($data['name']) ? $data['name'] : $this->buildTitleFromDescription($data));
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        return isset($data['picture']) && isset($data['picture']['data']['url']) ? array($data['picture']['data']['url']) : array();
    }

}