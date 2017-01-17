<?php

namespace ApiConsumer\Fetcher;


use ApiConsumer\LinkProcessor\PreprocessedLink;

class GoogleProfileFetcher extends AbstractFetcher{

    protected $url = 'plus/v1/people/';

    /**
     * Not usually used
     */
    public function fetchLinksFromUserFeed($token)
    {
        $this->setToken($token);
        $url = $this->getUrl($token['googleID']);
        $response = $this->resourceOwner->authorizedApiRequest($url, $this->getQuery(), $this->token);

        return $this->parseLinks($response);
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAsClient($username)
    {
        $url = $this->getUrl($username);

        $response = $this->resourceOwner->authorizedApiRequest($url, $this->getQuery(), $this->token);

        return $this->parseLinks($response);
    }

    public function getUrl($username = null)
    {
        return  $username ? $this->url . $username : $this->url;
    }

    protected function parseLinks($response)
    {
        $preprocessedLinks = array();
        if (isset($response['url'])){
            $preprocessedLinks[] = $this->buildPreprocessedLink($response['url']);
        }
        if (isset($response['urls'])){
            foreach ($response['urls'] as $url){
                $preprocessedLinks[] = $this->buildPreprocessedLink($url);
            }
        }

        return $preprocessedLinks;
    }

    protected function buildPreprocessedLink($url)
    {
        $preprocessedLink = new PreprocessedLink($url['value']);
        $preprocessedLink->setSource($this->resourceOwner->getName());
        return $preprocessedLink;
    }

}