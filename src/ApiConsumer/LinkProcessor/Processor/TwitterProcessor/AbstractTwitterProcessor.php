<?php

namespace ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\AbstractAPIProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\Link\Link;

abstract class AbstractTwitterProcessor extends AbstractAPIProcessor
{
    const TWITTER_LABEL = 'LinkTwitter';

    /** @var  TwitterUrlParser */
    protected $parser;

    /** @var  TwitterResourceOwner */
    protected $resourceOwner;

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $link->addAdditionalLabels(self::TWITTER_LABEL);
    }

    /**
     * @param $content array[]
     * @return Link[]
     */
    public function buildProfilesFromLookup(array $content)
    {
        foreach ($content as &$user) {
            $user = $this->buildProfileFromLookup($user);
        }

        return $content;
    }

    /**
     * @param $user array
     * @return Link
     */
    protected function buildProfileFromLookup(array $user)
    {
        if (!$user) {
            return null;
        }

        $profile = new Link();
        $profile->setUrl($this->parser->getUserUrl($user));
        $profile->setTitle(isset($user['name']) ? $user['name'] : $profile->getUrl());
        $profile->setDescription(isset($user['description']) ? $user['description'] : $profile->getTitle());
        $profile->setThumbnail($this->parser->getOriginalProfileUrl($user, null));
        $profile->setThumbnailMedium($this->parser->getMediumProfileUrl($user, null));
        $profile->setThumbnailSmall($this->parser->getSmallProfileUrl($user, null));
        $profile->setCreated(1000 * time());
        $profile->addAdditionalLabels(AbstractTwitterProcessor::TWITTER_LABEL);
        $profile->setProcessed(true);

        return $profile;
    }

}