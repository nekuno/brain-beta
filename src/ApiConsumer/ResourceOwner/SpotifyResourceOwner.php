<?php

namespace ApiConsumer\ResourceOwner;

use ApiConsumer\LinkProcessor\UrlParser\SpotifyUrlParser;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\SpotifyResourceOwner as SpotifyResourceOwnerBase;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Buzz\Message\RequestInterface as HttpRequestInterface;


class SpotifyResourceOwner extends SpotifyResourceOwnerBase
{
	use AbstractResourceOwnerTrait {
		AbstractResourceOwnerTrait::configureOptions as traitConfigureOptions;
		AbstractResourceOwnerTrait::__construct as private traitConstructor;
	}

    /** @var SpotifyUrlParser */
    protected $urlParser;

	public function __construct($httpClient, $httpUtils, $options, $name, $storage, $dispatcher)
	{
		$this->traitConstructor($httpClient, $httpUtils, $options, $name, $storage, $dispatcher);
	}

    public function canRequestAsClient()
    {
        return true;
    }

    /**
	 * {@inheritDoc}
	 */
	protected function configureOptions(OptionsResolverInterface $resolver)
	{
		$this->traitConfigureOptions($resolver);
		parent::configureOptions($resolver);

		$resolver->setDefaults(array(
			'base_url' => 'https://api.spotify.com/v1/',
		));

		$resolver->setDefined('redirect_uri');
	}

	/**
	 * {@inheritDoc}
	 */
	public function refreshAccessToken($token, array $extraParameters = array())
	{
		$refreshToken = $token['refreshToken'];
		$url = 'https://accounts.spotify.com/api/token';
		$authorization = base64_encode($this->options['consumer_key'] . ":" . $this->options['consumer_secret']);
		$headers = array('Authorization: Basic ' . $authorization);
		$body = array(
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
		);

		$response = $this->httpRequest($this->normalizeUrl($url, $body), null, $headers, HttpRequestInterface::METHOD_POST);

		return $this->getResponseContent($response);
	}

	public function requestTrack($trackId)
    {
        $urlTrack = 'tracks/' . $trackId;
        $track = $this->requestAsClient($urlTrack, array());

        return $track;
    }

    public function requestAlbum($albumId)
    {
        $urlAlbum = 'albums/' . $albumId;
        $album = $this->requestAsClient($urlAlbum, array());

        return $album;
    }

    public function requestArtist($artistId)
    {
        $urlArtist = 'artists/' . $artistId;
        $artist = $this->requestAsClient($urlArtist, array());

        return $artist;
    }

}
