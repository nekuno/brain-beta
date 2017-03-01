<?php

namespace Model\Link\Creator;

class CreatorTwitter extends Creator
{
    const CREATOR_TWITTER_DEFAULT_IMAGE = 'https://g.twimg.com/about/feature-corporate/image/twitterbird_RGB.png';

    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'] += array('CreatorTwitter');
        return $array;
    }

    /**
     * @return mixed
     */
    public function getThumbnail()
    {
        return $this->thumbnail ?: self::CREATOR_TWITTER_DEFAULT_IMAGE;
    }

    /**
     * @param mixed $thumbnail
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail ?: self::CREATOR_TWITTER_DEFAULT_IMAGE;
    }
}