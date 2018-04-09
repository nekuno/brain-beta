<?php

namespace ApiConsumer\Fetcher;

use Model\Token\Token;

class SpotifyPlaylistTracksFetcher extends SpotifyFetcher
{
    /**
     * { @inheritdoc }
     */
    public function fetchLinksFromUserFeed(Token $token)
    {
        $this->setUpToken($token);

        try {
            $playlists = $this->getPlaylists();
            $this->rawFeed = array();

            foreach ($playlists as $playlist) {
                $this->getTracks($playlist);
            }

        } catch (\Exception $e) {
            throw $e;
        }

        $links = $this->parseLinks($this->rawFeed);

        $this->addSourceAndToken($links, $token);

        return $links;
    }

    protected function getPlaylists()
    {
        $spotifyId = $this->getResourceId();

        $this->setQuery(array('limit' => $this::MAX_PLAYLISTS_PER_USER));
        $this->url = 'users/' . $spotifyId . '/playlists/';

        return $this->getLinksByPage();
    }

    protected function getTracks($playlist)
    {
        $spotifyId = $this->getResourceId();

        if ($playlist['owner']['id'] == $spotifyId) {

            $this->url = 'users/' . $spotifyId . '/playlists/' . $playlist['id'] . '/tracks';

            try {
                $this->setQuery(array('limit' => $this::MAX_TRACKS_PER_PLAYLIST));
                $this->getLinksByPage();

            } catch (\Exception $e) {
                return;
            }
        }
    }
}