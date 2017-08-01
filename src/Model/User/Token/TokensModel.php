<?php

namespace Model\User\Token;

use ApiConsumer\Event\OAuthTokenEvent;
use Event\AccountConnectEvent;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use HWI\Bundle\OAuthBundle\DependencyInjection\Configuration;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Model\User\Token\TokenStatus\TokenStatusManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TokensModel
{
    CONST FACEBOOK = 'facebook';
    CONST TWITTER = 'twitter';
    CONST GOOGLE = 'google';
    CONST SPOTIFY = 'spotify';
    CONST LINKEDIN = 'linkedin';

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var GraphManager
     */
    protected $gm;

    protected $tokenStatusManager;

    protected $validator;

    public function __construct(EventDispatcher $dispatcher, GraphManager $graphManager, TokenStatusManager $tokenStatusManager, \Service\Validator\ValidatorInterface $validator)
    {
        $this->dispatcher = $dispatcher;
        $this->gm = $graphManager;
        $this->tokenStatusManager = $tokenStatusManager;
        $this->validator = $validator;
    }

    public static function getResourceOwners()
    {
        return array(
            self::FACEBOOK,
            self::TWITTER,
            self::GOOGLE,
            self::SPOTIFY,
        );
    }

    /**
     * @param $id
     * @return Token[]
     */
    public function getAll($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:TOKEN_OF]-(token:Token)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->returns('user', 'token');

        $result = $qb->getQuery()->getResultSet();

        $return = array();
        /* @var $row Row */
        foreach ($result as $row) {
            $return[] = $this->buildFromRow($row);
        }

        return $return;
    }

    /**
     * @param int $id
     * @param string $resourceOwner
     * @return Token
     * @throws NotFoundHttpException
     */
    public function getById($id, $resourceOwner)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:TOKEN_OF]-(token:Token)')
            ->where('user.qnoow_id = { id }', 'token.resourceOwner = { resourceOwner }')
            ->setParameter('id', (integer)$id)
            ->setParameter('resourceOwner', $resourceOwner)
            ->returns('user', 'token')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Token not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $token = $this->buildFromRow($row);

        return $token;
    }

    /**
     * @param int $userId
     * @param string $resourceOwner
     * @param array $data
     * @return Token
     * @throws ValidationException|NotFoundHttpException|MethodNotAllowedHttpException
     */
    public function create($userId, $resourceOwner, array $data)
    {
        $data['resourceOwner'] = $resourceOwner;
        $this->validateOnCreate($data, $userId);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$userId)
            ->merge('(user)<-[:TOKEN_OF]-(token:Token {createdTime: { createdTime }})')
            ->setParameter('createdTime', time())
            ->returns('user', 'token');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();
        $tokenNode = $row->offsetGet('token');

        $this->saveTokenData($tokenNode, $data);
        $token = $this->getById($userId, $resourceOwner);

        $this->dispatcher->dispatch(\AppEvents::ACCOUNT_CONNECTED, new AccountConnectEvent($userId, $token));

        return $token;
    }

    /**
     * @param int $id
     * @param string $resourceOwner
     * @param array $data
     * @return Token
     * @throws ValidationException|NotFoundHttpException
     */
    public function update($id, $resourceOwner, array $data)
    {
        list($userNode, $tokenNode) = $this->getUserAndTokenNodesById($id, $resourceOwner);

        $data['resourceOwner'] = $resourceOwner;
        $this->validateOnUpdate($data, $id);

        $this->saveTokenData($tokenNode, $data);

        return $this->getById($id, $resourceOwner);
    }

    /**
     * @param int $userId
     * @param string $resourceOwner
     * @return Token
     */
    public function remove($userId, $resourceOwner)
    {
        $this->validateOnDelete($userId, $resourceOwner);
        $token = $this->getById($userId, $resourceOwner);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[token_of:TOKEN_OF]-(token:Token)')
            ->where('user.qnoow_id = { id }', 'token.resourceOwner = { resourceOwner }')
            ->setParameter('id', (integer)$userId)
            ->setParameter('resourceOwner', $resourceOwner)
            ->delete('token', 'token_of')
            ->returns('COUNT(token_of) AS count');

        $query = $qb->getQuery();
        $result = $query->getResultSet();
        /* @var $row Row */
        $row = $result->current();
        $count = $row->offsetGet('count');

        if ($count === 1) {
            $this->tokenStatusManager->removeOne($userId, $resourceOwner);
        }

        return $token;
    }

    /**
     * @param array $data
     * @param string $userId
     * @throws ValidationException
     */
    public function validateOnCreate(array $data, $userId = null)
    {
        if ($userId){
            $data['userId'] = $userId;
        }

        $this->validator->validateOnCreate($data);
    }

    public function validateOnUpdate(array $data, $userId)
    {
        $data['userId'] = $userId;
        $this->validator->validateOnUpdate($data);
    }

    public function validateOnDelete($userId, $resourceOwner)
    {
        $data = array('userId' => $userId, 'resourceOwner' => $resourceOwner);
        $this->validator->validateOnDelete($data);
    }

    public function getByLikedUrl($url, $resource)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(l:Link{url: {url}})')
            ->with('l')
            ->limit(1)
            ->setParameter('url', $url);

        $qb->match('(l)<-[:LIKES]-(user:User)<-[:TOKEN_OF]-(token:Token)')
            ->where('token.resourceOwner = {resource}')
            ->with('token', 'user')
            ->limit(1)
            ->setParameter('resource', $resource);

        $qb->returns('user', 'token');

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() == 0) {
            return null;
        }

        return $this->buildFromRow($result->current());
    }

    public function getOneByResource($resource)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(token:Token)')
            ->where('token.resourceOwner = {resource}')
            ->with('token')
            ->limit(1)
            ->setParameter('resource', $resource);

        $qb->match('(token)-[:TOKEN_OF]->(user:User)');

        $qb->returns('user', 'token');

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() == 0) {
            return null;
        }

        return $this->buildFromRow($result->current());
    }

    public function getUnconnectedNetworks($userId)
    {
        $tokens = $this->getAll($userId);
        $resourceOwners = $this->getResourceOwners();

        $unconnected = array();
        foreach ($resourceOwners as $resource) {
            $connected = false;
            foreach ($tokens as $token) {
                if ($token->getResourceOwner() == $resource) {
                    $connected = true;
                }
            }
            if (!$connected) {
                $unconnected[] = $resource;
            }
        }

        return $unconnected;

    }

    public function getConnectedNetworks($userId)
    {
        $tokens = $this->getAll($userId);

        $resourceOwners = array();
        foreach ($tokens as $token) {
            $resourceOwners[] = $token->getResourceOwner();
        }

        return $resourceOwners;
    }

    protected function buildFromRow(Row $row)
    {
        /* @var $userNode Node */
        $userNode = $row->offsetGet('user');
        /* @var $tokenNode Node */
        $tokenNode = $row->offsetGet('token');

        $token = new Token($tokenNode->getProperties());
        $token->setUserId($userNode->getProperty('qnoow_id'));

        return $token;
    }

    protected function getUserAndTokenNodesById($id, $resourceOwner)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(user)<-[:TOKEN_OF]-(token:Token)')
            ->where('token.resourceOwner = { resourceOwner }')
            ->setParameter('resourceOwner', $resourceOwner)
            ->returns('user', 'token')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            return array(null, null);
        }

        /* @var Row $row */
        $row = $result->current();
        $userNode = $row->offsetGet('user');
        $tokenNode = $row->offsetGet('token');

        return array($userNode, $tokenNode);
    }

    protected function saveTokenData(Node $tokenNode, array $data)
    {
        $token = new Token($data);

        $this->refreshTokenData($token);

        $resourceOwner = $token->getResourceOwner();

        if ($oauth1Token = $this->getOauth1Token($resourceOwner, $token->getOauthToken())) {
            $token->setOauthToken($oauth1Token['oauth_token']);
            $token->setOauthTokenSecret($oauth1Token['oauth_token_secret']);
        }

        $tokenNode->setProperty('resourceOwner', $resourceOwner);
        $tokenNode->setProperty('updatedTime', time());

        foreach ($token->toArray() as $property => $value) {
            if ($property == 'userId') {
                continue;
            }
            $tokenNode->setProperty($property, $value);
        }

        return $tokenNode->save();
    }

    protected function refreshTokenData($token)
    {
        $event = new OAuthTokenEvent($token);
        $this->dispatcher->dispatch(\AppEvents::TOKEN_PRE_SAVE, $event);
    }

    public function getOauth1Token($resourceOwner, $accessToken)
    {
        $type = Configuration::getResourceOwnerType($resourceOwner);
        if ($type == 'oauth1' && $accessToken) {
            $oauthToken = substr($accessToken, 0, strpos($accessToken, ':'));
            $oauthTokenSecret = substr($accessToken, strpos($accessToken, ':') + 1, strpos($accessToken, '@') - strpos($accessToken, ':') - 1);

            return array(
                'oauth_token' => $oauthToken,
                'oauth_token_secret' => $oauthTokenSecret,
            );
        }

        return null;
    }
}