<?php

namespace ApiConsumer\ResourceOwner;

use ApiConsumer\LinkProcessor\UrlParser\SpotifyUrlParser;
use Model\User\TokensModel;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\SpotifyResourceOwner as SpotifyResourceOwnerBase;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class FacebookResourceOwner
 *
 * @package ApiConsumer\ResourceOwner
 * @method SpotifyUrlParser getParser
 */
class SpotifyResourceOwner extends SpotifyResourceOwnerBase
{
	use AbstractResourceOwnerTrait {
		AbstractResourceOwnerTrait::configureOptions as traitConfigureOptions;
		AbstractResourceOwnerTrait::addOauthData as traitAddOauthData;
		AbstractResourceOwnerTrait::__construct as private traitConstructor;
	}

	protected $name = TokensModel::SPOTIFY;

	public function __construct($httpClient, $httpUtils, $options, $name, $storage, $dispatcher)
	{
		$this->traitConstructor($httpClient, $httpUtils, $options, $name, $storage, $dispatcher);
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

		$response = $this->httpRequest($url, $body, $headers);

		return $this->getResponseContent($response);
	}

	protected function addOauthData($data, $token)
	{
		$newToken = $this->traitAddOauthData($data, $token);
		if (!isset($newToken['refreshToken']) && isset($token['refreshToken'])){
			$newToken['refreshToken'] = $token['refreshToken'];
		}
		return $newToken;
	}

}
