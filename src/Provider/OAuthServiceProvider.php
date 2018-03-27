<?php

namespace Provider;

use Buzz\Client\Curl;
use HWI\Bundle\OAuthBundle\OAuth\RequestDataStorage\SessionStorage;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Provider\OAuthProvider;
use Pimple\Container;
use Security\Http\ResourceOwnerMap;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Http\HttpUtils;

class OAuthServiceProvider implements ServiceProviderInterface
{

    /**
     * { @inheritdoc }
     */
    public function register(Container $app)
    {
	    $app['user.checker'] = function () {

            return new UserChecker();
        };

        $app['hwi_oauth.http_client'] = function() {

            return new Curl();
        };

        $app['security.http_utils'] = function() {

            return new HttpUtils();
        };

        $app['hwi_oauth.storage.session'] = function() {

            $session = new Session();
            return new SessionStorage($session);
        };

	    // Create ResourceOwner's services
        foreach ($app['hwi_oauth']['resource_owners'] as $name => $options) {
            $app['hwi_oauth.resource_owner.' . $name] = function ($app) use ($name, $options) {
                unset($options['type']);
                $class = 'ApiConsumer\\ResourceOwner\\' . ucfirst($name) . 'ResourceOwner';

                return new $class($app['hwi_oauth.http_client'], $app['security.http_utils'], $options, $name, $app['hwi_oauth.storage.session'], $app['dispatcher']);
            };
        }

	    $app['oauth.resorcer_owner_map'] = function ($app) {

            $resourceOwnersMap = array();
            foreach ($app['hwi_oauth']['resource_owners'] as $name => $checkPath) {
                $resourceOwnersMap[$name] = "";
            }
            $resourceOwnerMap =  new ResourceOwnerMap($app['hwi_oauth']['resource_owners'], $resourceOwnersMap, $app);

            return $resourceOwnerMap;
        };

        $app['oauth.service'] = function ($app) {

            return new OAuthProvider($app['security.users_provider'], $app['oauth.resorcer_owner_map'], $app['user.checker']);
        };
    }
}
