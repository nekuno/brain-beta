<?php

namespace ApiConsumer\LinkProcessor\Processor\TumblrProcessor;

use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Audio;

class TumblrAudioProcessor extends TumblrPostProcessor
{

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::hydrateLink($preprocessedLink, $data);
        $link = $preprocessedLink->getFirstLink();
        $audio = Audio::buildFromArray($link->toArray());
        $title = isset($data['track_name']) ? $data['track_name'] : $data['source_title'];
        $audio->setTitle($title);
        $audio->setDescription($data['album'] . ' : ' . $data['artist']);
        $audio->setEmbedType('tumblr');
        $audio->setEmbedId($data['player']);

        $preprocessedLink->setFirstLink($audio);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        if (isset($data['album_art'])) {
            return array(new ProcessingImage($data['album_art']));
        }

        return parent::getImages($preprocessedLink, $data);
    }

    public function addTags(PreprocessedLink $preprocessedLink, array $data)
    {
        parent::addTags($preprocessedLink, $data);

        $link = $preprocessedLink->getFirstLink();

        if (isset($data['artist'])) {
            $link->addTag($this->buildArtistTag($data['artist']));
        }
        if (isset($data['album'])) {
            $link->addTag($this->buildAlbumTag($data['album']));
        }
        if (isset($data['track_name'])) {
            $link->addTag($this->buildSongTag($data['track_name']));
        }
    }

    protected function buildArtistTag($artist)
    {
        $tag = array();
        $tag['name'] = $artist;
        $tag['additionalLabels'][] = 'Artist';

        return $tag;
    }

    protected function buildAlbumTag($album)
    {
        $tag = array();
        $tag['name'] = $album;
        $tag['additionalLabels'][] = 'Album';

        return $tag;
    }

    protected function buildSongTag($trackName) {
        $tag = array();
        $tag['name'] = $trackName;
        $tag['additionalLabels'][] = 'Song';

        return $tag;
    }
}