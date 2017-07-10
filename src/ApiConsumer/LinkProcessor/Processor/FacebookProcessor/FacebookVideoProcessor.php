<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\User\Token\TokensModel;
use Model\Link\Video;

class FacebookVideoProcessor extends AbstractFacebookProcessor
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $id = $this->getItemId($preprocessedLink->getUrl());
        $preprocessedLink->setResourceItemId($id);

        if ($preprocessedLink->getSource() == TokensModel::FACEBOOK) {
            $response = $this->resourceOwner->requestVideo($id, $preprocessedLink->getToken());
        } else {
            $response = array();
        }

        return $response;
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $video = new Video();
        $video->setDescription(isset($data['description']) ? $data['description'] : null);
        $video->setTitle($this->buildTitleFromDescription($data));
        $video->setEmbedType(TokensModel::FACEBOOK);
        $video->setEmbedId($preprocessedLink->getResourceItemId());

        $preprocessedLink->setFirstLink($video);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $picture = $this->resourceOwner->requestPicture($preprocessedLink->getResourceItemId(), $preprocessedLink->getToken());
        return $picture ? array($picture) : array($this->brainBaseUrl . self::DEFAULT_IMAGE_PATH);
    }

    protected function getItemIdFromParser($url)
    {
        return $this->parser->getVideoId($url);
    }

}