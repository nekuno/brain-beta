<?php

namespace ApiConsumer\LinkProcessor;

use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\LinkProcessor\UrlParser\FacebookUrlParser;
use ApiConsumer\LinkProcessor\UrlParser\SpotifyUrlParser;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\LinkProcessor\UrlParser\UrlParser;
use ApiConsumer\LinkProcessor\UrlParser\UrlParserInterface;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Model\User\Token\TokensModel;

class LinkAnalyzer
{
    /**
     * @param PreprocessedLink $link
     * @return string
     */
    public static function getProcessorName(PreprocessedLink $link)
    {
        if ($link->getType()) {
            return $link->getType();
        }

        $url = $link->getUrl();

        try{
            $parser = self::getUrlParser($url);
            $type = $parser->getUrlType($url);
        } catch (UrlNotValidException $e){
            $type = UrlParser::SCRAPPER;
        }

        return $type;
    }

    public static function getResource($url) {

        if (self::isYouTube($url)) {
            return TokensModel::GOOGLE;
        }

        if (self::isSpotify($url)) {
            return TokensModel::SPOTIFY;
        }

        if (self::isFacebook($url)) {
            return TokensModel::FACEBOOK;
        }

        if (self::isTwitter($url)) {
            return TokensModel::TWITTER;
        }

        return null;
    }

    public static function mustResolve(PreprocessedLink $link)
    {
        return !self::isSpotify($link->getUrl());
    }

    /**
     * @param $url
     * @return UrlParserInterface|bool
     */
    //TODO: This is functionally a UrlParserFactory. Probably better to create.
    protected static function getUrlParser($url)
    {
        (new UrlParser())->checkUrlValid($url);

        if (self::isYouTube($url)) {
            return new YoutubeUrlParser();
        }

        if (self::isSpotify($url)) {
            return new SpotifyUrlParser();
        }

        if (self::isFacebook($url)) {
            return new FacebookUrlParser();
        }

        if (self::isTwitter($url)) {
            return new TwitterUrlParser();
        }

        return new UrlParser();
    }

    public static function cleanUrl($url)
    {
        $parser = self::getUrlParser($url);

        return $parser->cleanURL($url);
    }

    public static function isTextSimilar($text1, $text2)
    {
        $similarTextPercentage = 30;

        similar_text($text1, $text2, $percent);

        return $percent > $similarTextPercentage;
    }

    public static function getUsername($url)
    {
        $parser = self::getUrlParser($url);

        return $parser->getUsername($url);
    }
    
    //TODO: Improve detection on host, not whole url
    private static function isFacebook($url)
    {
        return strpos($url, 'facebook.com') !== false;
    }

    private static function isTwitter($url)
    {
        return strpos($url, 'twitter.com') !== false;
    }

    private static function isSpotify($url)
    {
        return strpos($url, 'spotify.com') !== false;
    }

    private static function isYouTube($url)
    {
        return strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false;
    }

}