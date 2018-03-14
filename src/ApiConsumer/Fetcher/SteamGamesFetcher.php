<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\SteamUrlParser;
use ApiConsumer\ResourceOwner\SteamResourceOwner;
use Model\Link\Link;
use Model\Token\Token;

class SteamGamesFetcher extends AbstractFetcher
{
    protected $url = 'IPlayerService/GetOwnedGames/v1';

    protected function getQuery()
    {
        return array(
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
        );
    }

    public function fetchLinksFromUserFeed(Token $token)
    {
        $this->setToken($token);

        /** @var SteamResourceOwner $resourceOwner */
        $resourceOwner = $this->resourceOwner;
        $response = $resourceOwner->requestAsUser($this->url, $this->getQuery(), $token);

        $games = isset($response['response']['games']) ? $response['response']['games'] : array();

        return $this->parseLinks($games);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAsClient($username)
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    protected function parseLinks(array $response)
    {
        $preprocessedLinks = array();

        foreach ($response as $item) {
            $type = SteamUrlParser::getGameProcessor();
            $link = array(
                'id' => $item['appid'],
                'url' => "https://store.steampowered.com/app/" . $item['appid'],
                'title' => $item['name'],
                'thumbnail' => $this->getThumbnail($item),
            );
            $preprocessedLink = new PreprocessedLink($link['url']);
            $preprocessedLink->setFirstLink(Link::buildFromArray($link));
            $preprocessedLink->setType($type);
            $preprocessedLink->setSource($this->resourceOwner->getName());
            $preprocessedLink->setResourceItemId($item['appid']);
            $preprocessedLink->setToken($this->getToken());
            $preprocessedLinks[] = $preprocessedLink;
        }

        return $preprocessedLinks;
    }

    public function getThumbnail($item)
    {
        $gameId = $item['appid'];
        if (isset($item['img_logo_url']) && $item['img_logo_url']) {
            $hash = $item['img_logo_url'];
            return "http://media.steampowered.com/steamcommunity/public/images/apps/$gameId/$hash.jpg";
        }

        return null;
    }
}