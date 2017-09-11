<?php

namespace ApiConsumer\LinkProcessor\Processor\FacebookProcessor;

use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
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
        $default = $this->brainBaseUrl . FacebookUrlParser::DEFAULT_IMAGE_PATH;
        $itemId = $preprocessedLink->getResourceItemId();
        $token = $preprocessedLink->getToken();

        $pictureLarge = $this->resourceOwner->requestLargePicture($itemId, $token);
        $imageLarge = new ProcessingImage($pictureLarge?: $default);
        $pictureSmall = $this->resourceOwner->requestSmallPicture($itemId, $token);
        $imageSmall = new ProcessingImage($pictureSmall?: $default);

        $images = array($imageLarge);
        if ($pictureSmall !== $pictureLarge) {
            $images[] = $imageSmall;
        }

        return $images;
    }

    protected function getItemIdFromParser($url)
    {
        return $this->parser->getVideoId($url);
    }

}