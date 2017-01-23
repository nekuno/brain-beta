<?php

namespace ApiConsumer\ResourceOwner;

use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use Buzz\Exception\RequestException;
use Buzz\Message\Response;
use Model\User\Token\Token;
use Model\User\Token\TokensModel;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\TwitterResourceOwner as TwitterResourceOwnerBase;

class TwitterResourceOwner extends TwitterResourceOwnerBase
{
    use AbstractResourceOwnerTrait {
        AbstractResourceOwnerTrait::configureOptions as traitConfigureOptions;
        AbstractResourceOwnerTrait::__construct as private traitConstructor;
    }

    /** @var  TwitterUrlParser */
    protected $urlParser;

    const PROFILES_PER_LOOKUP = 100;

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

        $resolver->setDefaults(
            array(
                'base_url' => 'https://api.twitter.com/1.1/',
                'realm' => null,
            )
        );
    }

    public function authorizedAPIRequest($url, array $query = array())
    {
        $clientToken = $this->getOption('client_credential')['application_token'];
        $url = $this->getOption('base_url') . $url;

        $headers = array();
        if (!empty($clientToken)) {
            $headers = array('Authorization: Bearer ' . $clientToken);
        }

        $response = $this->httpRequest($this->normalizeUrl($url, $query), null, array(), $headers);

        return $this->getResponseContent($response);
    }

    public function refreshAccessToken($token, array $extraParameters = array())
    {
        $refreshToken = $token['refreshToken'];
        $url = 'https://accounts.google.com/o/oauth2/token';
        $parameters = array(
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->options['consumer_key'],
            'client_secret' => $this->options['consumer_secret'],
        );

        $response = $this->httpRequest($url, array('body' => $parameters));
        $data = $this->getResponseContent($response);

        return $data;
    }

    public function lookupUsersBy($parameter, array $userIds, Token $token = null)
    {
        if (!in_array($parameter, array('user_id', 'screen_name'))) {
            return false;
        }

        $chunks = array_chunk($userIds, self::PROFILES_PER_LOOKUP);
        $url = $this->options['base_url'] . 'users/lookup.json';

        $responses = array();
        //TODO: Array to string conversion here
        foreach ($chunks as $chunk) {
            $query = array($parameter => implode(',', $chunk));
            $response = $this->sendAuthorizedRequest($url, $query, $token);
            $responses[] = $response;
        }

        return $responses;
    }

    protected function isAPILimitReached(Response $response)
    {
        return $response->getStatusCode() === 429;
    }

    protected function waitForAPILimit()
    {
        $fifteenMinutes = 60 * 15;
        sleep($fifteenMinutes);
    }

    public function buildProfilesFromLookup(Response $response)
    {
        $content = $this->getResponseContent($response);

        foreach ($content as &$user) {
            $user = $this->buildProfileFromLookup($user);
        }

        return $content;
    }

    public function buildProfileFromLookup($user)
    {
        if (!$user) {
            return $user;
        }

        $profile = array(
            'description' => isset($user['description']) ? $user['description'] : $user['name'],
            'url' => isset($user['screen_name']) ? $this->urlParser->buildUserUrl($user['screen_name']) : null,
            'thumbnail' => isset($user['profile_image_url']) ? str_replace('_normal', '', $user['profile_image_url']) : null,
            'additionalLabels' => array('Creator'),
            'resource' => TokensModel::TWITTER,
            'timestamp' => 1000 * time(),
            'processed' => 1
        );
        $profile['title'] = isset($user['name']) ? $user['name'] : $profile['url'];

        return $profile;
    }

    public function getProfileUrl(Token $token)
    {
        try {
            $settingsUrl = 'account/settings.json';
            $account = $this->authorizedHttpRequest($settingsUrl, array(), $token);
        } catch (RequestException $e) {
            return null;
        }

        if (!isset($account['screen_name'])) {
            return null;
        }
        $screenName = $account['screen_name'];

        return $this->urlParser->buildUserUrl($screenName);
    }

    public function requestStatus($statusId)
    {
        $query = array('id' => (int)$statusId);
        $apiResponse = $this->authorizedAPIRequest('statuses/show.json', $query);

        return $apiResponse;
    }

//	public function dispatchChannel(array $data)
//	{
//		$url = isset($data['url']) ? $data['url'] : null;
//		$username = isset($data['username']) ? $data['username'] : null;
//		if (!$username && $url) {
//			throw new \Exception ('Cannot add twitter channel with username and url not set');
//		}
//
//		$this->dispatcher->dispatch(\AppEvents::CHANNEL_ADDED, new ChannelEvent($this->getName(), $url, $username));
//	}
}
