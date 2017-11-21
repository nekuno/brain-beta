<?php

namespace ApiConsumer\LinkProcessor\UrlParser;

class TumblrUrlParser extends UrlParser
{
    const TUMBLR_BLOG = 'tumblr_blog';
    const TUMBLR_AUDIO = 'tumblr_audio';
    const TUMBLR_VIDEO = 'tumblr_video';
    const TUMBLR_PHOTO = 'tumblr_photo';
    const TUMBLR_LINK = 'tumblr_link';
    const TUMBLR_POST = 'tumblr_post';
    const DEFAULT_IMAGE_PATH = 'default_images/tumblr.png';

    public function getUrlType($url)
    {
        if ($this->isBlogUrl($url)) {
            return self::TUMBLR_BLOG;
        }

        return self::TUMBLR_POST;
    }

    static public function getBlogId($url)
    {
        return preg_replace('/https?:\/\//', '', trim($url, '/'));
    }

    public function isBlogUrl($url)
    {
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['path'])) {
            $path = explode('/', trim($parsedUrl['path'], '/'));

            if (count($path) > 0) {
                return false;
            }
        }

        return true;
    }

    public function isPostUrl($url)
    {
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['path'])) {
            $path = explode('/', trim($parsedUrl['path'], '/'));

            if (count($path) > 0) {
                return true;
            }
        }

        return false;
    }
}