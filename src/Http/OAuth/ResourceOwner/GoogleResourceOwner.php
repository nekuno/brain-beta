<?php

namespace Http\OAuth\ResourceOwner;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class FacebookResourceOwner
 *
 * @package ApiConsumer\ResourceOwner
 */
class GoogleResourceOwner extends Oauth2GenericResourceOwner
{
    protected $name = 'google';

    /**
     * {@inheritDoc}
     */
    protected function configureOptions(OptionsResolverInterface $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'base_url' => 'https://www.googleapis.com/',
        ));
    }

    public function refreshAccessToken($refreshToken, array $extraParameters = array())
    {
        $url = 'https://accounts.google.com/o/oauth2/token';
        $parameters = array(
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->options['consumer_key'],
            'client_secret' => $this->options['consumer_secret'],
        );

        $response = $this->httpClient->post($url, array('body' => $parameters));
        $data = $response->json();

        return $data;
    }
}