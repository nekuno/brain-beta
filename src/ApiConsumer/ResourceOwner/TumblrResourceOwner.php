<?php

namespace ApiConsumer\ResourceOwner;

use ApiConsumer\LinkProcessor\UrlParser\TumblrUrlParser;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use Buzz\Exception\RequestException;
use Buzz\Message\Response;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\AbstractResourceOwner;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth1ResourceOwner;
use Model\User\Token\Token;
use Model\User\Token\TokensModel;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class TumblrResourceOwner extends GenericOAuth1ResourceOwner
{
    use AbstractResourceOwnerTrait {
        AbstractResourceOwnerTrait::configureOptions as traitConfigureOptions;
        AbstractResourceOwnerTrait::__construct as private traitConstructor;
    }

    /** @var  TwitterUrlParser */
    protected $urlParser;

    public function __construct($httpClient, $httpUtils, $options, $name, $storage, $dispatcher)
    {
        $this->traitConstructor($httpClient, $httpUtils, $options, $name, $storage, $dispatcher);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInformation(array $accessToken, array $extraParameters = array())
    {
        return parent::getUserInformation($accessToken, $extraParameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolverInterface $resolver)
    {
        $this->traitConfigureOptions($resolver);
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'base_url' => 'https://api.tumblr.com/v2/',
            'authorization_url' => 'https://www.tumblr.com/oauth/authorize',
            'request_token_url' => 'https://www.tumblr.com/oauth/request_token',
            'access_token_url' => 'https://www.tumblr.com/oauth/access_token',
            'infos_url' => 'https://api.tumblr.com/v2/user/info',
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function httpRequest($url, $content = null, $parameters = array(), $headers = array(), $method = null)
    {
        foreach ($parameters as $key => $value) {
            $parameters[$key] = $key . '="' . rawurlencode($value) . '"';
        }

        if (!$this->options['realm']) {
            array_unshift($parameters, 'realm="' . rawurlencode($this->options['realm']) . '"');
        }

        return AbstractResourceOwner::httpRequest($url, $content, array(), $method);
    }


    public function requestAsClient($url, array $query = array())
    {
        $clientToken = $this->getOption('client_credential')['application_token'];
        $url = $this->getOption('base_url') . $url;

        $headers = array();
        $query['api_key'] = $clientToken;

        $response = $this->httpRequest($this->normalizeUrl($url, $query), null, array(), $headers);

        return $this->getResponseContent($response);
    }

    public function canRequestAsClient()
    {
        return true;
    }

    public function refreshAccessToken($token, array $extraParameters = array())
    {
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

    public function requestBlog($blogId, Token $token = null)
    {
        $url = "blog/$blogId/info";

        if ($token && $token->getResourceOwner() === TokensModel::TUMBLR) {
            return $this->request($url, array(), $token);
        }

        return $this->requestAsClient($url);
    }

    public function requestBlogAvatar($blogId, $size, Token $token = null)
    {
        try {
            $url = "blog/$blogId/avatar/$size";
            if ($token && $token->getResourceOwner() === TokensModel::TUMBLR) {
                $response = $this->requestAsUser($url, array(), $token);
            } else {
                $response = $this->requestAsClient($url);
            }
        } catch (RequestException $e) {
            return TumblrUrlParser::DEFAULT_IMAGE_PATH;
        }

        return isset($response['errors']) ? TumblrUrlParser::DEFAULT_IMAGE_PATH : $this->options['base_url'] . $url;
    }

    public function requestPost($blogId, $postId, Token $token = null)
    {
        $url = "blog/$blogId/posts";
        $query = array(
            'id' => $postId
        );

        if ($token && $token->getResourceOwner() === TokensModel::TUMBLR) {
            return $this->request($url, $query, $token);
        }

        return $this->requestAsClient($url, $query);
    }

    public function requestPosts($blogId, Token $token = null)
    {
        $url = "blog/$blogId/posts";

        return $this->request($url, array(), $token);
    }
}
