<?php

namespace Service;

use HWI\Bundle\OAuthBundle\OAuth\Exception\HttpTransportException;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Provider\OAuthProvider;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Manager\UserManager;
use Model\User;
use Model\User\Token\TokensModel;
use ReflectionObject;
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
     * @param $refreshToken
     * @return string
     */
    public function loginByResourceOwner($resourceOwner, $accessToken, $refreshToken = null)
    {
        $accessToken = $this->tokensModel->getOauth1Token($resourceOwner, $accessToken) ?: $accessToken;

        $token = new OAuthToken($accessToken);
        $token->setResourceOwnerName($resourceOwner);

        try {
            $newToken = $this->getNewToken($token);
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        if (!$newToken) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.');
        }

        $user = $this->updateLastLogin($newToken->getUser());

        $data = array('oauthToken' => $accessToken);
        if ($refreshToken) {
            $data['refreshToken'] = $refreshToken;
        }

        try {
            $this->tokensModel->update($user->getId(), $resourceOwner, $data);
        } catch (\Exception $e) {

        }
        return $this->buildToken($user);
    }

    protected function getNewToken($token, $counter = 0) {
        $newToken = null;
        if ($counter >= 5) {
            return $newToken;
        }

        try {
            $newToken = $this->oAuthProvider->authenticate($token);
        }
        catch (HttpTransportException $e) {
            sleep(1);
            $counter++;
            $newToken = $this->getNewToken($token, $counter);
        }
        catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'Los datos introducidos no coinciden con nuestros registros.', $e);
        }

        return $newToken;
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

    public function getUser($token)
    {
        /** @var \stdClass $data */
        $data = $this->jwtEncoder->decode($token);

        $user = new User();
        $this->cast($user, $data->user);

        return $user;
    }

    protected function cast($destination, $sourceObject)
    {
        if (is_string($destination)) {
            $destination = new $destination();
        }
        $sourceReflection = new ReflectionObject($sourceObject);
        $destinationReflection = new ReflectionObject($destination);
        $sourceProperties = $sourceReflection->getProperties();
        foreach ($sourceProperties as $sourceProperty) {
            $sourceProperty->setAccessible(true);
            $name = $sourceProperty->getName();
            $value = $sourceProperty->getValue($sourceObject);
            if ($destinationReflection->hasProperty($name)) {
                $propDest = $destinationReflection->getProperty($name);
                $propDest->setAccessible(true);
                $propDest->setValue($destination,$value);
            } else {
                $destination->$name = $value;
            }
        }
        return $destination;
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