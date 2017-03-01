<?php

namespace Service;

use Event\AccountConnectEvent;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Provider\OAuthProvider;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Manager\UserManager;
use Model\User;
use Model\User\Token\Token;
use Model\User\Token\TokensModel;
use Silex\Component\Security\Core\Encoder\JWTEncoder;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

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
        $accessToken = $this->tokensModel->getOauth1Token($resourceOwner, $accessToken) ?: $accessToken;

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
        $this->updateToken($user, $resourceOwner, $accessToken);

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

    protected function updateToken(User $user, $resourceOwner, $accessToken)
    {
        $savedToken = $this->tokensModel->getById($user->getId(), $resourceOwner);

        $loginToken = new Token();
        $loginToken->setOauthToken($accessToken);
        $loginToken->setUserId($user->getId());
        $loginToken->setResourceOwner($resourceOwner);
        $loginToken->setResourceId($savedToken->getResourceId());

        $this->dispatcher->dispatch(\AppEvents::ACCOUNT_UPDATED, new AccountConnectEvent($user->getId(), $loginToken));
    }

}