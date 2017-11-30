<?php

namespace ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\BatchProcessorInterface;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\Link\Creator;
use Model\Link\Link;
use Model\User\Token\Token;
use Model\User\Token\TokensModel;

class TwitterProfileProcessor extends AbstractTwitterProcessor implements BatchProcessorInterface
{
    protected function requestItem(PreprocessedLink $preprocessedLink)
    {
        $userId = $this->getUserId($preprocessedLink);
        $token = $preprocessedLink->getSource() == TokensModel::TWITTER ? $preprocessedLink->getToken() : null;
        $key = array_keys($userId)[0];

        $response = $this->resourceOwner->lookupUsersBy($key, array($userId[$key]), $token);

        return $response;
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $profiles = $this->resourceOwner->buildProfilesFromLookup($data);
        $profileArray = reset($profiles);
        $link = Link::buildFromArray($profileArray);
        $preprocessedLink->setFirstLink($link);
        $id = isset($data['id_str']) ? (int)$data['id_str'] : $data['id'];
        $preprocessedLink->setResourceItemId($id);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $default = $this->brainBaseUrl . TwitterUrlParser::DEFAULT_IMAGE_PATH;

        $largeThumbnailUrl = $this->parser->getOriginalProfileUrl($data, $default);
        $largeImage = $this->buildSquareImage($largeThumbnailUrl, ProcessingImage::LABEL_LARGE);

        $mediumThumbnailUrl = $this->parser->getMediumProfileUrl($data, $default);
        $mediumImage = $this->buildSquareImage($mediumThumbnailUrl, ProcessingImage::LABEL_MEDIUM, 73);

        $smallThumbnailUrl = $this->parser->getSmallProfileUrl($data, $default);
        $smallImage = $this->buildSquareImage($smallThumbnailUrl, ProcessingImage::LABEL_SMALL, 48);

        return array($smallImage, $mediumImage, $largeImage);
    }

    protected function buildSquareImage($url, $label, $size = null)
    {
        $image = new ProcessingImage($url);
        $image->setLabel($label);
        $image->setWidth($size);
        $image->setHeight($size);

        return $image;
    }

    protected function getUserId(PreprocessedLink $preprocessedLink)
    {
        return $this->getItemId($preprocessedLink->getUrl());
    }

    protected function getItemIdFromParser($url)
    {
        return $this->parser->getProfileId($url);
    }

    public function needToRequest(array $batch)
    {
        return count($batch) >= TwitterResourceOwner::PROFILES_PER_LOOKUP;
    }

    /**
     * @param array $batch
     * @return Creator[]
     */
    public function requestBatchLinks(array $batch)
    {
        $userIds = $this->getUserIdsFromBatch($batch);

        $token = $this->getTokenFromBatch($batch);

        $userArrays = $this->requestLookup($userIds, $token);

        if (empty($userArrays)) {
            return array();
        }

        $links = $this->buildLinks($userArrays);

        return $links;
    }

    /**
     * @param PreprocessedLink[] $batch
     * @return array
     */
    protected function getUserIdsFromBatch(array $batch)
    {
        $userIds = array('user_id' => array(), 'screen_name' => array());
        foreach ($batch as $key => $preprocessedLink) {

            $link = $preprocessedLink->getFirstLink();

            if ($preprocessedLink->getSource() == TokensModel::TWITTER
                && $link && $link->isComplete() && !($link->getProcessed() !== false)
            ) {
                unset($batch[$key]);
            }

            $userId = $this->parser->getProfileId($preprocessedLink->getUrl());
            $key = array_keys($userId)[0];
            $userIds[$key][] = $userId[$key];
        }

        return $userIds;
    }

    /**
     * @param $batch PreprocessedLink[]
     * @return Token
     */
    protected function getTokenFromBatch(array $batch)
    {
        foreach ($batch as $preprocessedLink) {
            if ($token = $preprocessedLink->getToken()) {
                return $token;
            }
        }

        return null;
    }

    protected function requestLookup(array $userIds, Token $token = null)
    {
        $userArrays = array();
        foreach ($userIds as $key => $ids) {
            $userArrays = array_merge($userArrays, $this->resourceOwner->lookupUsersBy($key, $ids, $token));
        }

        return $userArrays;
    }

    protected function buildLinks(array $userArrays)
    {
        $linkArrays = $this->resourceOwner->buildProfilesFromLookup($userArrays);
        $links = array();
        foreach ($linkArrays as $linkArray) {
            $links[] = Creator::buildFromArray($linkArray);
        }

        return $links;
    }

}