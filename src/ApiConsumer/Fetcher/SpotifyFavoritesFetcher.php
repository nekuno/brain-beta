<?php

namespace ApiConsumer\Fetcher;

class SpotifyFavoritesFetcher extends SpotifyFetcher
{
    const MAX_FAVORITES = 100;

    protected $query = array('limit' => self::MAX_FAVORITES);

    public function getUrl()
    {
        $spotifyId = $this->getResourceId();

        return 'users/' . $spotifyId . '/starred/tracks';
    }
}