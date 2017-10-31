<?php

namespace ApiConsumer\ResourceOwner;

use ApiConsumer\LinkProcessor\UrlParser\SpotifyUrlParser;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\InstagramResourceOwner as InstagramResourceOwnerBase;
use Model\User\Token\Token;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class InstagramResourceOwner extends InstagramResourceOwnerBase
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
            'base_url'                  => 'https://api.instagram.com/v1/',
        ));

		$resolver->setDefined('redirect_uri');
	}

    public function requestMedia($itemId, Token $accessToken)
    {
        $url = 'media/' . $itemId;

        return $this->request($url, array(), $accessToken);
    }

    public function requestProfile($itemId, Token $accessToken)
    {
        $url = 'users/' . $itemId;

        return $this->request($url, array(), $accessToken);
    }
}
