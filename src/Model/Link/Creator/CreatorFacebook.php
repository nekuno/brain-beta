<?php

namespace Model\Link\Creator;

class CreatorFacebook extends Creator
{
    const CREATOR_FACEBOOK_DEFAULT_IMAGE = 'https://www.facebook.com/images/fb_icon_325x325.png';

    public function toArray()
    {
        $array = parent::toArray();
        $array['additionalLabels'] += array('CreatorFacebook');
        return $array;
    }

    /**
     * @return mixed
     */
    public function getThumbnail()
    {
        return $this->thumbnail ?: self::CREATOR_FACEBOOK_DEFAULT_IMAGE;
    }

    /**
     * @param mixed $thumbnail
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail ?: self::CREATOR_FACEBOOK_DEFAULT_IMAGE;
    }
}