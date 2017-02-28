<?php

namespace Service;

use Event\AccountConnectEvent;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Provider\OAuthProvider;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Manager\UserManager;
use Model\User;
use Model\User\Token\TokensModel;
use Silex\Component\Security\Core\Encoder\JWTEncoder;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use HWI\Bundle\OAuthBundle\DependencyInjection\Configuration;

class AuthService
{

    /**
     * @var UserManager
     */
    protected $um;

    /**
     * @var PasswordEncoderInterface
     */
    protected $encoder;

    /**
     * @var JWTEncoder
     */
    protected $jwtEncoder;

    /**
     * @var OAuthProvider
     */
    protected $oAuthProvider;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var TokensModel
     */
    protected $tokensModel;

    public function __construct(UserManager $um, PasswordEncoderInterface $encoder, JWTEncoder $jwtEncoder, OAuthProvider $oAuthProvider, EventDispatcher $dispatcher, TokensModel $tokensModel)
    {
        $this->um = $um;
        $this->encoder = $encoder;
        $this->jwtEncoder = $jwtEncoder;
        $this->oAuthProvider = $oAuthProvider;
        $this->dispatcher = $dispatcher;
        $this->tokensModel = $tokensModel;
    }

    /**
     * @param $username
     * @param $password
     * @return string
     * @throws UnauthorizedHttpException
     */
    public function login($username, $password)
    {

        try {
            $user = $this->um->findUserBy(array('usernameCanonical' => $this->um->canonicalize($username)));
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        $encodedPassword = $user->getPassword();
        $salt = $user->getSalt();
        $valid = $this->encoder->isPasswordValid($encodedPassword, $password, $salt);

        if (!$valid) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        $user = $this->updateLastLogin($user);

        return $this->buildToken($user);
    }

    /**
     * @param $resourceOwner
     * @param $accessToken
     * @return string
     * @throws UnauthorizedHttpException
     */
    public function loginByResourceOwner($resourceOwner, $accessToken)
    {
        $accessToken = $this->fixOauth1Token($resourceOwner, $accessToken);

        $token = new OAuthToken($accessToken);
        $token->setResourceOwnerName($resourceOwner);

        try {
            $newToken = $this->oAuthProvider->authenticate($token);
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        if (!$newToken) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        $user = $this->updateLastLogin($newToken->getUser());
        $savedToken = $this->tokensModel->getById($user->getId(), $resourceOwner);
        $this->dispatcher->dispatch(\AppEvents::ACCOUNT_UPDATED, new AccountConnectEvent($user->getId(), $savedToken));

        return $this->buildToken($user);
    }

    /**
     * @param string $id
     * @return string
     */
    public function getToken($id)
    {
        $user = $this->um->getById($id);

        return $this->buildToken($user);
    }

    protected function fixOauth1Token($resourceOwner, $accessToken)
    {
        $type = Configuration::getResourceOwnerType($resourceOwner);
        if ($type == 'oauth1') {
            $oauthToken = substr($accessToken, 0, strpos($accessToken, ':'));
            $oauthTokenSecret = substr($accessToken, strpos($accessToken, ':') + 1, strpos($accessToken, '@') - strpos($accessToken, ':') - 1);

            $accessToken = array(
                'oauth_token' => $oauthToken,
                'oauth_token_secret' => $oauthTokenSecret,
            );
        }

        return $accessToken;
    }

    /**
     * @param User $user
     * @return string
     */
    protected function buildToken(User $user)
    {
        $token = array(
            'iss' => 'https://nekuno.com',
            'sub' => $user->getUsernameCanonical(),
            'user' => $user->jsonSerialize(),
        );

        $jwt = $this->jwtEncoder->encode($token);

        return $jwt;
    }

    protected function updateLastLogin(User $user)
    {

        $data = array(
            'userId' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'lastLogin' => (new \DateTime())->format('Y-m-d H:i:s'),
        );

        return $this->um->update($data);
    }

}