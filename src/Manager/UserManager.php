<?php

namespace Manager;

use Cocur\Slugify\Slugify;
use Event\UserEvent;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\ResultSet;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Model\Neo4j\Neo4jException;
use Model\User;
use Model\User\GhostUser\GhostUserManager;
use Model\User\Group\Group;
use Model\User\LookUpModel;
use Model\User\SocialNetwork\SocialProfile;
use Model\User\Token\TokensModel;
use Model\User\UserComparedStatsModel;
use Model\User\UserStatusModel;
use Paginator\PaginatedInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserManager implements PaginatedInterface
{

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var PasswordEncoderInterface
     */
    protected $encoder;

    /**
     * @var PhotoManager
     */
    protected $pm;

    /**
     * @var Slugify
     */
    protected $slugify;

    /**
     * @var string
     */
    protected $imagesBaseDir;

    public function __construct(EventDispatcher $dispatcher, GraphManager $gm, PasswordEncoderInterface $encoder, PhotoManager $pm, Slugify $slugify, $imagesBaseDir)
    {
        $this->dispatcher = $dispatcher;
        $this->gm = $gm;
        $this->encoder = $encoder;
        $this->pm = $pm;
        $this->slugify = $slugify;
        $this->imagesBaseDir = $imagesBaseDir;
    }

    /**
     * Returns an empty user instance
     *
     * @return User
     */
    public function createUser()
    {
        $user = new User();

        return $user;
    }

    /**
     * @param bool $includeGhosts
     * @return User[]
     * @throws Neo4jException
     */
    public function getAll($includeGhosts = false)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)');
        if (!$includeGhosts) {
            $qb->where('NOT (u:GhostUser)');
        }
        $qb->returns('u')
            ->orderBy('u.qnoow_id');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $return = array();

        foreach ($result as $row) {
            $return[] = $this->build($row);
        }

        return $return;
    }

    /**
     * @param bool $includeGhosts
     * @return array
     */
    public function getAllIds($includeGhosts = false)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)');
        if (!$includeGhosts) {
            $qb->where('NOT (u:GhostUser)');
        }
        $qb->returns('u.qnoow_id AS id');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $this->buildIdsArray($result);
    }

    public function getMostSimilarIds($userId, $userLimit)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User{qnoow_id:{userId}})')
            ->setParameter('userId', $userId);
        $qb->with('u')
            ->limit(1);

        $qb->match('(u)-[s:SIMILARITY]-(u2:User)')
            ->where('NOT (u2:GhostUser)')
            ->with('s.similarity AS similarity', 'u2.qnoow_id AS id')
            ->orderBy(' 1 - similarity ASC')// similarity DESC starts with NULL values
            ->limit('{limit}')
            ->setParameter('limit', $userLimit)
            ->returns('id');

        $result = $qb->getQuery()->getResultSet();

        return $this->buildIdsArray($result);
    }

    public function buildIdsArray(ResultSet $result)
    {
        $ids = array();
        foreach ($result as $row) {
            $ids[] = $row->offsetGet('id');
        }

        return $ids;
    }

    /**
     * @param $id
     * @param bool $includeGhost
     * @return User
     * @throws Neo4jException
     * @throws NotFoundHttpException
     */
    public function getById($id, $includeGhost = false)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (int)$id);
        if (!$includeGhost) {
            $qb->where('NOT u:' . GhostUserManager::LABEL_GHOST_USER);
        }

        $qb->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException(sprintf('User "%d" not found', $id));
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    /**
     * @param $slug
     * @param bool $includeGhost
     * @return User
     * @throws Neo4jException
     * @throws NotFoundHttpException
     */
    public function getBySlug($slug, $includeGhost = false)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {slug: { slug }})')
            ->setParameter('slug', $slug);
        if (!$includeGhost) {
            $qb->where('NOT u:' . GhostUserManager::LABEL_GHOST_USER);
        }

        $qb->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    /**
     * @param $slug
     * @return User
     * @throws Neo4jException
     * @throws NotFoundHttpException
     */
    public function getPublicBySlug($slug)
    {
        $result = $this->getResultBySlug($slug);
        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->buildPublic($row);
    }

    /**
     * @param array $criteria
     * @return User
     * @throws Neo4jException
     * @throws NotFoundHttpException
     */
    public function findUserBy(array $criteria = array())
    {

        if (empty($criteria)) {
            throw new NotFoundHttpException('Criteria can not be empty');
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)');

        $wheres = array();
        foreach ($criteria as $field => $value) {
            $wheres[] = 'u.' . $field . ' = { ' . $field . ' }';
        }
        $qb->where($wheres)
            ->setParameters($criteria)
            ->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    /**
     * @param string $resourceOwner
     * @param string $resourceId
     * @return User
     * @throws Neo4jException
     * @throws NotFoundHttpException
     */
    public function findUserByResourceOwner($resourceOwner, $resourceId)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)<-[:TOKEN_OF]-(t:Token)')
            ->where('t.resourceOwner = { resourceOwner }', 't.resourceId = { resourceId }')
            ->setParameters(
                array(
                    'resourceOwner' => $resourceOwner,
                    'resourceId' => $resourceId,
                )
            )
            ->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    /**
     * Finds a user by email
     *
     * @param string $email
     *
     * @return UserInterface
     */
    public function findUserByEmail($email)
    {
        return $this->findUserBy(array('emailCanonical' => $this->canonicalize($email)));
    }

    /**
     * Finds a user by username
     *
     * @param string $username
     *
     * @return UserInterface
     */
    public function findUserByUsername($username)
    {
        return $this->findUserBy(array('usernameCanonical' => $this->canonicalize($username)));
    }

    /**
     * Finds a user either by email, or username
     *
     * @param string $usernameOrEmail
     *
     * @return UserInterface
     */
    public function findUserByUsernameOrEmail($usernameOrEmail)
    {
        if (filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->findUserByEmail($usernameOrEmail);
        }

        return $this->findUserByUsername($usernameOrEmail);
    }

    /**
     * Finds a user either by confirmation token
     *
     * @param string $token
     *
     * @return UserInterface
     */
    public function findUserByConfirmationToken($token)
    {
        return $this->findUserBy(array('confirmationToken' => $token));
    }

    public function validate(array $data, $isUpdate = false)
    {

        $errors = array();

        $metadata = $this->getMetadata($isUpdate);

        foreach ($metadata as $fieldName => $fieldData) {

            $fieldErrors = array();

            if (isset($fieldData['editable']) && $fieldData['editable'] === false) {
                continue;
            }

            if (!isset($data[$fieldName]) || !$data[$fieldName]) {
                if (isset($fieldData['required']) && $fieldData['required'] === true) {
                    $fieldErrors[] = sprintf('"%s" is required', $fieldName);
                }
            } else {

                $fieldValue = $data[$fieldName];

                switch ($fieldData['type']) {
                    case 'integer':
                        if (!is_integer($fieldValue)) {
                            $fieldErrors[] = sprintf('"%s" must be an integer', $fieldName);
                        }
                        break;
                    case 'string':
                        if (!is_string($fieldValue)) {
                            $fieldErrors[] = sprintf('"%s" must be a string', $fieldName);
                        }
                        break;
                    case 'photo':
                        if (!is_string($fieldValue)) {
                            $fieldErrors[] = sprintf('"%s" must be a string', $fieldName);
                        }
                        break;
                    case 'boolean':
                        if ($fieldValue !== true && $fieldValue !== false) {
                            $fieldErrors[] = 'Must be a boolean.';
                        }
                        break;
                    case 'datetime':
                        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $fieldValue);
                        if (!($date && $date->format('Y-m-d H:i:s') == $fieldValue)) {
                            $fieldErrors[] = 'Invalid datetime format, valid format is "Y-m-d H:i:s".';
                        }
                        break;
                    case 'array':
                        if (!is_array($fieldValue)) {
                            $fieldErrors[] = sprintf('"%s" must be an array', $fieldName);
                        }
                        break;
                }
            }

            if (count($fieldErrors) > 0) {
                $errors[$fieldName] = $fieldErrors;
            }

        }

        $public = array();
        foreach ($metadata as $fieldName => $fieldData) {
            if (!(isset($fieldData['editable']) && $fieldData['editable'] === false)) {
                $public[$fieldName] = $fieldData;
            }
        }

        if ($isUpdate && !isset($data['userId'])) {
            $errors['userId'] = array('user ID is not defined');
        }

        if (isset($data['userId'])) {
            $public['userId'] = $data['userId'];
        }

        $diff = array_diff_key($data, $public);
        if (count($diff) > 0) {
            foreach ($diff as $invalidKey => $invalidValue) {
                $errors[$invalidKey] = array(sprintf('Invalid key "%s"', $invalidKey));
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    public function validateUsername($userId, $username)
    {
        $username = trim($username);
        if (!ctype_alnum(str_replace('_', '', $username))) {
            throw new ValidationException(
                array(
                    'username' => array('Invalid username. Only letters, numbers and _ are valid.')
                )
            );
        }
        if (strlen($username) > 25) {
            throw new ValidationException(
                array(
                    'username' => array('Invalid username. Max 25 characters.')
                )
            );
        }
        $user = array('username' => $username);
        $this->updateCanonicalFields($user);
        foreach (array('usernameCanonical' => $user['usernameCanonical'], 'slug' => $user['slug']) as $key => $value) {
            $qb = $this->gm->createQueryBuilder();
            $qb->match("(u:User { $key: { value$key }})")
                ->where('u.qnoow_id <> { userId }')
                ->setParameters(
                    array(
                        'userId' => $userId,
                        "value$key" => $value,
                    )
                )
                ->returns('u AS users')
                ->limit(1);

            $query = $qb->getQuery();
            $result = $query->getResultSet();

            if ($result->count() > 0 || !$username) {
                throw new ValidationException(
                    array(
                        'username' => array('Invalid username. Username already exists')
                    )
                );
            }
        }
    }

    /**
     * @param array $data
     * @return User
     * @throws Neo4jException
     */
    public function create(array $data)
    {
        $this->validate($data);

        $data['userId'] = $this->getNextId();
        $data['username'] = $this->getVerifiedUsername(trim($data['username']));

        $qb = $this->gm->createQueryBuilder();
        $qb->create('(u:User:UserEnabled)')
            ->set('u.qnoow_id = { qnoow_id }')
            ->setParameter('qnoow_id', $data['userId'])
            ->set('u.status = { status }')
            ->setParameter('status', UserStatusModel::USER_STATUS_INCOMPLETE)
            ->set('u.createdAt = { createdAt }')
            ->setParameter('createdAt', (new \DateTime())->format('Y-m-d H:i:s'));

        $qb->getQuery()->getResultSet();

        $this->setDefaults($data);

        $this->createPhoto($data['userId'], $data);
        $user = $this->save($data);

        $this->dispatcher->dispatch(\AppEvents::USER_CREATED, new UserEvent($user));

        return $user;
    }

    /**
     * @param array $data
     * @return User
     */
    public function update(array $data)
    {
        $this->validate($data, true);
        unset($data['photo']);

        if (isset($data['username'])) {
            $data['username'] = trim($data['username']);
            $this->validateUsername($data['userId'], $data['username']);
        }

        $user = $this->save($data);

        $this->dispatcher->dispatch(\AppEvents::USER_UPDATED, new UserEvent($user));

        return $user;
    }

    public function setEnabled($userId, $enabled, $fromAdmin = false)
    {
        $conditions = array('u.qnoow_id = { qnoow_id }');
        if (!$fromAdmin){
            $conditions[] = 'NOT u.canReenable = false';
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)')
            ->where($conditions)
            ->with('u')
            ->limit(1)
            ->setParameter('qnoow_id', (integer)$userId);

        $label = $enabled ? 'UserEnabled' : 'UserDisabled';
        $qb->remove('u:UserEnabled:UserDisabled');
        $qb->set("u:$label");

        $qb->returns('u');

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() == 0) {
            throw new NotFoundHttpException(sprintf('User "%d" not found', $userId));
        }

        return true;
    }

    public function isEnabled($userId)
    {
        $user = $this->getById($userId);

        return $user->isEnabled();
    }

    public function setCanReenable($userId, $canReenable)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)')
            ->where('u.qnoow_id = { qnoow_id }')
            ->with('u')
            ->limit(1)
            ->setParameter('qnoow_id', (integer)$userId);

        $qb->set('u.canReenable = { canReenable }')
            ->setParameter('canReenable', (Boolean)$canReenable);

        $qb->returns('u');

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() == 0) {
            throw new NotFoundHttpException(sprintf('User "%d" not found', $userId));
        }

        return true;
    }

    /**
     * @param bool $includeGhost
     * @param integer $groupId
     * @return \ArrayAccess
     * @throws Neo4jException
     */
    public function getAllCombinations($includeGhost = true, $groupId = null)
    {
        $conditions = array('u1.qnoow_id < u2.qnoow_id');
        if (!$includeGhost) {
            $conditions[] = 'NOT u1:' . GhostUserManager::LABEL_GHOST_USER;
            $conditions[] = 'NOT u2:' . GhostUserManager::LABEL_GHOST_USER;
        }
        $qb = $this->gm->createQueryBuilder();

        if ($groupId) {
            $qb->setParameter('groupId', (integer)$groupId);
            $qb->match('(g:Group)')
                ->where('id(g) = {groupId}')
                ->with('g')
                ->limit(1);
            $qb->match('(u1)-[:BELONGS_TO]-(g), (u2)-[:BELONGS_TO]-(g)');
        } else {
            $qb->match('(u1:User), (u2:User)');
        }
        $qb->where($conditions);

        $qb->returns('u1.qnoow_id, u2.qnoow_id');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $result;
    }

    /**
     * @param $id
     * @param int $limit
     * @return User[]
     * @throws Neo4jException
     */
    public function getByCommonLinksWithUser($id, $limit = 100)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters(array('limit' => (integer)$limit));

        $qb->match('(ref:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->match('(ref)-[:LIKES|DISLIKES]->(:Link)<-[l:LIKES]-(u:User)')
            ->where('NOT (ref.qnoow_id = u.qnoow_id)')
            ->with('u', 'count(l) as amount')
            ->orderBy('amount DESC')
            ->limit('{limit}')
            ->returns('DISTINCT u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $this->buildMany($result);
    }

    /**
     * @param $questionId
     * @param int $limit
     * @return User[]
     */
    public function getByQuestionAnswered($questionId, $limit = 100)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User)-[:RATES]->(q:Question)')
            ->setParameter('question', (int)$questionId)
            ->where('id(q) = {question}')
            ->returns('DISTINCT u')
            ->limit('{limit}')
            ->setParameter('limit', (int)$limit);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $this->buildMany($result);

    }

    /**
     * @param $userId
     * @param int $limit
     * @return User[]
     */
    public function getByUserQuestionAnswered($userId, $limit = 100)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: {userId}})-[:RATES]->(q:Question)')
            ->setParameter('userId', (int)$userId)
            ->with('u, q')
            ->match('(o:User)-[:RATES]->(q)')
            ->where('u <> o')
            ->returns('DISTINCT o')
            ->limit('{limit}')
            ->setParameter('limit', (int)$limit);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $this->buildMany($result);

    }

    /**
     * @param $groupId
     * @param array $data
     * @return User[]
     * @throws Neo4jException
     */
    public function getByGroup($groupId, array $data = array())
    {
        $qb = $this->gm->createQueryBuilder();

        $parameters = array('groupId' => $groupId);

        $qb->match('(g:Group)')
            ->where('id(g) = {groupId}')
            ->match('(u:User)-[:BELONGS_TO]->(g)');
        if (isset($data['userId'])) {
            $qb->where('NOT u.qnoow_id = {userId}');
            $parameters['userId'] = (integer)$data['userId'];
        }
        $qb->returns('u');
        if (isset($data['limit'])) {
            $parameters['limit'] = (integer)$data['limit'];
            $qb->limit('{limit}');
        }

        $qb->setParameters($parameters);

        $query = $qb->getQuery();

        return $this->buildMany($query->getResultSet());
    }

    public function getByCreatedGroup($groupId)
    {
        $qb = $this->gm->createQueryBuilder();

        $parameters = array('groupId' => $groupId);

        $qb->match('(g:Group)')
            ->where('id(g) = {groupId}')
            ->match('(u:User)-[:CREATED_GROUP]->(g)')
            ->returns('u')
            ->limit(1);
        $qb->setParameters($parameters);
        $result = $qb->getQuery()->getResultSet();

        return $this->build($result->current());
    }

    /**
     * @param $id
     * @return User
     * @throws Neo4jException
     */
    public function getOneByThread($id)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->match('(thread)<-[:HAS_THREAD]-(u:User)')
            ->returns('u');
        $qb->setParameter('id', (integer)$id);
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() == 0) {
            throw new NotFoundHttpException('Thread ' . $id . ' does not exist or is not from an user.');
        }

        return $this->build($result->current());
    }

    /**
     * @param SocialProfile $profile
     * @return User
     * @throws Neo4jException
     */
    public function getBySocialProfile(SocialProfile $profile)
    {
        $labels = array_keys(LookUpModel::$resourceOwners, $profile->getResource());

        if (empty($labels)) {
            $labels = array(LookUpModel::LABEL_SOCIAL_NETWORK);
        }

        foreach ($labels as $label) {
            $qb = $this->gm->createQueryBuilder();

            $qb->match("(sn:$label)")
                ->match('(u:User)-[hsn:HAS_SOCIAL_NETWORK]->(sn)')
                ->where('hsn.url = {url}');
            $qb->returns('u');

            $qb->setParameters(
                array(
                    'url' => $profile->getUrl(),
                )
            );

            $query = $qb->getQuery();
            $resultSet = $query->getResultSet();

            if ($resultSet->count() == 1) {
                $row = $resultSet->current();

                return $this->build($row);
            }
        }

        return null;
    }

    /**
     * @param $userId
     * @param array $resources
     * @return User[]
     */
    public function getFollowingFrom($userId, $resources = array())
    {
        $qb = $this->gm->createQueryBuilder();

        $resourceStrings = array();
        foreach ($resources as $resource) {
            $resourceStrings[] = "EXISTS(likes.$resource)";
        }
        $resourceString = implode(' OR ', $resourceStrings);

        $qb->match('(u:User{qnoow_id: {userId}})')
            ->with('(u)');
        $qb->setParameter('userId', (integer)$userId);
        $qb->match('(u)-[likes:LIKES]-(c:Creator)');
        if (!empty($resourceStrings)) {
            $qb->where($resourceString);
        }
        $qb->with('collect(c.url) AS urls');
        $qb->match('(u2:User)-[hsn:HAS_SOCIAL_NETWORK]-()')
            ->where('hsn.url IN urls');
        $qb->returns('u2 AS u');

        $result = $qb->getQuery()->getResultSet();

        $users = array();
        foreach ($result as $row) {
            $users[] = $this->build($row);
        }

        return $users;
    }

    /**
     * @param $id
     * @return UserStatusModel
     * @throws NotFoundHttpException
     */
    public function getStatus($id)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb
            ->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->returns('u.status AS status');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $row->offsetGet('status');

    }

    /**
     * @param $id1
     * @param $id2
     * @return UserComparedStatsModel
     * @throws \Exception
     */
    public function getComparedStats($id1, $id2)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->setParameters(
            array(
                'id1' => (integer)$id1,
                'id2' => (integer)$id2
            )
        );

        $qb->match('(u:User {qnoow_id: { id1 }}), (u2:User {qnoow_id: { id2 }})')
            ->optionalMatch('(u)-[:BELONGS_TO]->(g:Group)<-[:BELONGS_TO]-(u2)')
            ->with('u', 'u2', 'collect(distinct g) AS groupsBelonged')
            ->optionalMatch('(u)-[:TOKEN_OF]-(token:Token)')
            ->with('u', 'u2', 'groupsBelonged', 'collect(distinct token.resourceOwner) as resourceOwners')
            ->optionalMatch('(u2)-[:TOKEN_OF]-(token2:Token)');
        $qb->with('u, u2', 'groupsBelonged', 'resourceOwners', 'collect(distinct token2.resourceOwner) as resourceOwners2')
            ->optionalMatch('(u)-[:LIKES]->(link:Link)')
            ->where('(u2)-[:LIKES]->(link)')
            ->with('u', 'u2', 'groupsBelonged', 'resourceOwners', 'resourceOwners2', 'count(distinct(link)) AS commonContent')
            ->optionalMatch('(u)-[:ANSWERS]->(answer:Answer)')
            ->where('(u2)-[:ANSWERS]->(answer)')
            ->returns('groupsBelonged, resourceOwners, resourceOwners2, commonContent, count(distinct(answer)) as commonAnswers');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        $groups = array();
        foreach ($row->offsetGet('groupsBelonged') as $groupNode) {
            $groups[] = Group::createFromNode($groupNode);
        }

        $resourceOwners = array();
        foreach ($row->offsetGet('resourceOwners') as $resourceOwner) {
            $resourceOwners[] = $resourceOwner;
        }
        $resourceOwners2 = array();
        foreach ($row->offsetGet('resourceOwners2') as $resourceOwner2) {
            $resourceOwners2[] = $resourceOwner2;
        }

        $commonContent = $row->offsetGet('commonContent') ?: 0;
        $commonAnswers = $row->offsetGet('commonAnswers') ?: 0;

        $userStats = new UserComparedStatsModel(
            $groups,
            $resourceOwners,
            $resourceOwners2,
            $commonContent,
            $commonAnswers
        );

        return $userStats;
    }

    /**
     * @param integer $id
     * @param bool $set
     * @return UserStatusModel
     * @throws NotFoundHttpException
     */
    public function calculateStatus($id, $set = true)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb
            ->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(u)-[:ANSWERS]->(a:Answer)')
            ->optionalMatch('(u)-[:LIKES]->(l:Link)')
            ->returns('u.status AS status', 'COUNT(DISTINCT a) AS answerCount', 'COUNT(DISTINCT l) AS linkCount');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException(sprintf('User "%d" not found', $id));
        }

        /* @var $row Row */
        $row = $result->current();

        $status = new UserStatusModel($row['status'], $row['answerCount'], $row['linkCount']);

        if ($status->getStatus() !== $row['status']) {
            $status->setStatusChanged();

            if ($set) {
                $qb = $this->gm->createQueryBuilder();
                $qb
                    ->match('(u:User {qnoow_id: { id }})')
                    ->setParameter('id', (integer)$id)
                    ->set('u.status = { status }')
                    ->setParameter('status', $status->getStatus())
                    ->returns('u');

                $query = $qb->getQuery();
                $query->getResultSet();
            }

        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function validateFilters(array $filters)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function slice(array $filters, $offset, $limit)
    {
        $response = array();

        $parameters = array(
            'offset' => (integer)$offset,
            'limit' => (integer)$limit
        );

        $profileQuery = "";
        if (isset($filters['profile'])) {
            $profileQuery = " MATCH (user)-[:PROFILE_OF]-(profile:Profile) ";
            if (isset($filters['profile']['zodiacSign'])) {
                $profileQuery .= "
                    MATCH (profile)-[:OPTION_OF]-(zodiacSign:ZodiacSign)
                    WHERE id(zodiacSign) = {zodiacSign}
                ";
                $parameters['zodiacSign'] = $filters['profile']['zodiacSign'];
            }
            if (isset($filters['profile']['gender'])) {
                $profileQuery .= "
                    MATCH (profile)-[:OPTION_OF]-(gender:Gender)
                    WHERE id(gender) = {gender}
                ";
                $parameters['gender'] = $filters['profile']['gender'];
            }
            if (isset($filters['profile']['orientation'])) {
                $profileQuery .= "
                    MATCH (profile)-[:OPTION_OF]-(orientation:Orientation)
                    WHERE id(orientation) = {orientation}
                ";
                $parameters['orientation'] = $filters['profile']['orientation'];
            }
        }

        $referenceUserQuery = "";
        $resultQuery = " RETURN user ";
        if (isset($filters['referenceUserId'])) {
            $parameters['referenceUserId'] = (integer)$filters['referenceUserId'];
            $referenceUserQuery = "
                MATCH
                (referenceUser:User)
                WHERE
                referenceUser.qnoow_id = {referenceUserId} AND
                user.qnoow_id <> {referenceUserId}
                OPTIONAL MATCH
                (user)-[match:MATCHES]-(referenceUser)
                OPTIONAL MATCH
                (user)-[similarity:SIMILARITY]-(referenceUser)
             ";
            $resultQuery .= ", match, similarity ";
        }

        $query = "
            MATCH
            (user:User)"
            . $profileQuery
            . $referenceUserQuery
            . $resultQuery
            . "
            SKIP {offset}
            LIMIT {limit}
            ;
         ";

        $contentQuery = $this->gm->createQuery($query, $parameters);

        $result = $contentQuery->getResultSet();

        foreach ($result as $row) {

            $user = $this->build($row);

            $user['matching'] = 0;
            if (isset($row['match'])) {
                /** @var Relationship $matchRelationship */
                $matchRelationship = $row['match'];
                $matchingByQuestions = $matchRelationship->getProperty('matching_questions');
                $user['matching'] = null === $matchingByQuestions ? 0 : $matchingByQuestions;
            }

            $user['similarity'] = 0;
            if (isset($row['similarity'])) {
                /** @var Relationship $similarityRelationship */
                $similarityRelationship = $row['similarity'];
                $similarity = $similarityRelationship->getProperty('similarity');
                $user['similarity'] = null === $similarity ? 0 : $similarity;
            }

            $response[] = $user;
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function countTotal(array $filters)
    {

        $parameters = array();

        $queryWhere = '';
        if (isset($filters['referenceUserId'])) {
            $parameters['referenceUserId'] = (integer)$filters['referenceUserId'];
            $queryWhere .= " WHERE user.qnoow_id <> {referenceUserId} ";
        }

        if (isset($filters['profile'])) {
            //TODO: Profile filters
        }

        $query = "
            MATCH
            (user:User)
            " . $queryWhere . "
            RETURN
            count(user) as total
            ;
        ";

        $contentQuery = $this->gm->createQuery($query, $parameters);

        $result = $contentQuery->getResultSet();
        $row = $result->current();
        $count = $row['total'];

        return $count;
    }

    protected function getMetadata($isUpdate = false)
    {
        $metadata = array(
            'qnoow_id' => array('type' => 'string', 'editable' => false),
            'username' => array('type' => 'string', 'editable' => true),
            'usernameCanonical' => array('type' => 'string', 'editable' => false),
            'email' => array('type' => 'string'),
            'emailCanonical' => array('type' => 'string', 'editable' => false),
            'salt' => array('type' => 'string', 'editable' => false),
            'password' => array('type' => 'string', 'editable' => false),
            'plainPassword' => array('type' => 'string', 'visible' => false),
            'lastLogin' => array('type' => 'datetime'),
            'locked' => array('type' => 'boolean', 'default' => false),
            'expired' => array('type' => 'boolean', 'editable' => false),
            'expiresAt' => array('type' => 'datetime'),
            'confirmationToken' => array('type' => 'string'),
            'passwordRequestedAt' => array('type' => 'datetime'),
            'createdAt' => array('type' => 'datetime', 'editable' => false),
            'updatedAt' => array('type' => 'datetime', 'editable' => false),
            'confirmed' => array('type' => 'boolean', 'default' => false),
            'status' => array('type' => 'string', 'editable' => false),
            'photo' => array('type' => 'photo'),
            'tutorials' => array('type' => 'array'),
        );

        if ($isUpdate) {
            $metadata['plainPassword']['required'] = false;
        }

        return $metadata;
    }

    public function save(array $data)
    {
        $userId = $data['userId'];
        unset($data['userId']);

        $this->updateCanonicalFields($data);
        $this->updatePassword($data);

        $data['updatedAt'] = (new \DateTime())->format('Y-m-d H:i:s');

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', $userId)
            ->with('u');

        foreach ($data as $key => $value) {
            $qb->set("u.$key = { $key }")
                ->setParameter($key, $value);
        }

        $qb->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    public function fuseUsers($userId1, $userId2)
    {
        return $this->gm->fuseNodes($this->getNodeId($userId1), $this->getNodeId($userId2));
    }

    public function setSlugs(OutputInterface $output)
    {
        $users = $this->getAll();

        foreach ($users as $user) {
            if (!$user->getUsernameCanonical() && $user->getSlug() == '') {
                $qb = $this->gm->createQueryBuilder();
                $qb->match("(u:User {qnoow_id: { id }})")
                    ->setParameter("id", $user->getId())
                    ->remove("u.slug")
                    ->returns("u");

                $query = $qb->getQuery();
                $query->getResultSet();

                $output->writeln("Removed void slug for user " . $user->getUsername() . " (" . $user->getId() . ")");
                continue;
            }
            $slug = $this->slugify->slugify($user->getUsernameCanonical(), '_');
            $qb = $this->gm->createQueryBuilder();
            $qb->match("(u:User {qnoow_id: { id }})")
                ->setParameter("id", $user->getId())
                ->set("u.slug = { slug }")
                ->setParameter("slug", $slug)
                ->returns("u.slug");

            $query = $qb->getQuery();
            $query->getResultSet();

            $output->writeln("Slug " . $slug . " set for user " . $user->getUsername() . " (" . $user->getId() . ")");
        }

        return count($users);
    }

    public function build(Row $row)
    {
        /* @var $node Node */
        $node = $row->offsetGet('u');
        $user = $this->createUser();

        $this->buildNodeProperties($user, $node);
        $this->buildPhoto($user, $node);
        $user->setEnabled($this->isNodeEnabled($node));

        return $user;
    }

    /**
     * @param ResultSet $resultSet
     * @return User[]
     */
    public function buildMany(ResultSet $resultSet)
    {
        $users = array();
        foreach ($resultSet as $row) {
            $users[] = $this->build($row);
        }

        return $users;
    }

    protected function buildNodeProperties(User $user, Node $node)
    {
        $properties = $this->getBuildingProperties($node);

        foreach ($properties as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($user, $method)) {
                $value = $this->modifyByType($key, $value);
                $user->{$method}($value);
            }
        }
    }

    protected function getBuildingProperties(Node $node)
    {
        $properties = $node->getProperties();

        if (isset($properties['qnoow_id'])) {
            $properties['id'] = $properties['qnoow_id'];
            unset($properties['qnoow_id']);
        }
        unset($properties['photo']);
        unset($properties['enabled']); //TODO: Remove after a database query

        return $properties;
    }

    protected function modifyByType($key, $value)
    {
        $metadata = $this->getMetadata();

        if (!isset($metadata[$key])) {
            return $value;
        }

        switch ($metadata[$key]['type']) {
            case 'datetime':
                $value = new \DateTime($value);
                break;
            default:
                break;
        }

        return $value;
    }

    protected function buildPhoto(User $user, Node $node)
    {
        $photo = $this->pm->createProfilePhoto();
        $photo->setUserId($user->getId());
        $photo->setPath($node->getProperty('photo'));
        $user->setPhoto($photo);
    }

    protected function isNodeEnabled(Node $node)
    {
        return $this->gm->hasLabelName($node, 'UserEnabled');
    }

    public function buildPublic(Row $row)
    {

        /* @var $node Node */
        $node = $row->offsetGet('u');
        $properties = $node->getProperties();
        if (isset($properties['qnoow_id'])) {
            $properties['id'] = $properties['qnoow_id'];
            unset($properties['qnoow_id']);
        }
        $user = $this->createUser();
        $photo = $this->pm->createProfilePhoto();
        $photo->setUserId($user->getId());
        $user->setPhoto($photo);
        $user->setUsername($user->getUsername());

        foreach ($properties as $key => $value) {
            switch ($key) {
                case 'id':
                    $user->setId($value);
                    break;
                case 'username':
                    $user->setUsername($value);
                    break;
                case 'slug':
                    $user->setSlug($value);
                    break;
                case 'photo':
                    $photo->setPath($value);
                    break;
            }
        }

        return $user;
    }

    public function getNextId()
    {

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)')
            ->returns('u.qnoow_id AS id')
            ->orderBy('id DESC')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $id = 1;
        if ($result->count() > 0) {
            /* @var $row Row */
            $row = $result->current();
            $id = $row->offsetGet('qnoow_id') + 1;
        }

        return $id;
    }

    protected function getVerifiedUsername($username)
    {
        $exists = true;
        $suffix = 1;
        $username = $username ?: 'user1';

        while ($exists) {
            try {
                $this->validateUsername(0, $username);
                $exists = false;
            } catch (ValidationException $e) {
                $username = 'user' . $suffix++;
            }
        }

        return $username;
    }

    public function canonicalize($string)
    {
        return null === $string ? null : mb_convert_case($string, MB_CASE_LOWER, mb_detect_encoding($string));
    }

    public function isChannel($userId, $resource)
    {
        $channelLabel = $this->buildChannelLabel($resource);
        $labels = $this->getLabelsFromId($userId);

        if (in_array($channelLabel, $labels)) {
            return true;
        }

        return false;
    }

    public function setAsChannel($userId, $resource)
    {
        $channelLabel = $this->buildChannelLabel($resource);

        return $this->setLabel($userId, $channelLabel);
    }

    public function deleteOtherUserFields($userArray)
    {
        unset($userArray['password']);
        unset($userArray['salt']);
        unset($userArray['confirmationToken']);
        unset($userArray['confirmed']);
        unset($userArray['createdAt']);
        unset($userArray['credentialsExpireAt']);
        unset($userArray['credentialsExpired']);
        unset($userArray['email']);
        unset($userArray['emailCanonical']);
        unset($userArray['expired']);
        unset($userArray['expiresAt']);
        unset($userArray['locked']);
        unset($userArray['passwordRequestedAt']);
        unset($userArray['roles']);
        unset($userArray['status']);
        unset($userArray['tutorials']);
        unset($userArray['updatedAt']);
        unset($userArray['lastLogin']);

        return $userArray;
    }

    protected function buildChannelLabel($resource = null)
    {
        if (in_array($resource, TokensModel::getResourceOwners())) {
            return 'Channel' . ucfirst($resource);
        }

        return null;
    }

    protected function getLabelsFromId($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (int)$id);

        $qb->returns('labels(u) as labels');

        $rs = $qb->getQuery()->getResultSet();

        if ($rs->count() == 0) {
            throw new NotFoundHttpException('User to get labels from not found');
        }

        $labelsRow = $rs->current()->offsetGet('labels');
        $labels = array();
        foreach ($labelsRow as $label) {
            $labels[] = $label;
        }

        return $labels;
    }

    protected function setLabel($id, $label)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (int)$id);
        $qb->set("u :$label");

        $qb->returns('u');

        $rs = $qb->getQuery()->getResultSet();

        if ($rs->count() == 0) {
            throw new NotFoundHttpException(sprintf('User to set label %s not found', $label));
        }

        return $this->build($rs->current());
    }

    protected function setDefaults(array &$user)
    {
        foreach ($this->getMetadata() as $fieldName => $fieldData) {
            if (!array_key_exists($fieldName, $user) && isset($fieldData['default'])) {
                $user[$fieldName] = $fieldData['default'];
            }
        }
    }

    protected function updateCanonicalFields(array &$user)
    {
        if (isset($user['username'])) {
            $user['usernameCanonical'] = $this->canonicalize($user['username']);
        }
        if (isset($user['email'])) {
            $user['emailCanonical'] = $this->canonicalize($user['email']);
        }
        if (isset($user['usernameCanonical'])) {
            $user['slug'] = $this->slugify->slugify($user['usernameCanonical'], '_');
        }
    }

    protected function updatePassword(array &$user)
    {

        if (isset($user['plainPassword'])) {
            $user['salt'] = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
            $user['password'] = $this->encoder->encodePassword($user['plainPassword'], $user['salt']);
            unset($user['plainPassword']);
        }
    }

    protected function createPhoto($userId, array &$data)
    {
        $defaultImageUrl = $this->imagesBaseDir . 'bundles/qnoowlanding/images/user-no-img.jpg';
        if (isset($data['photo']) && filter_var($data['photo'], FILTER_VALIDATE_URL)) {
            $url = $data['photo'];
        } else {
            $url = $defaultImageUrl;
        }
        $user = $this->getById($userId);

        try {
            $photo = $this->pm->create($user, @file_get_contents($url));
        } catch (\Exception $e) {
            $photo = $this->pm->create($user, @file_get_contents($defaultImageUrl));
        }
        $profilePhoto = $this->pm->setAsProfilePhoto($photo, $user);
        $data['photo'] = $profilePhoto->getPath();

        if (!$profilePhoto) {
            unset($data['photo']);
        }

    }

    private function getResultBySlug($slug)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {slug: { slug }})')
            ->setParameter('slug', $slug)
            ->where('NOT u:' . GhostUserManager::LABEL_GHOST_USER);

        $qb->returns('u');
        $query = $qb->getQuery();

        return $query->getResultSet();
    }

    private function createSlug($slug)
    {
        $usernameCanonical = urldecode($slug);
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User{usernameCanonical: { usernameCanonical }})')
            ->setParameter('usernameCanonical', $usernameCanonical)
            ->where('NOT u:' . GhostUserManager::LABEL_GHOST_USER)
            ->set('u.slug = { slug }')
            ->setParameter('slug', $slug)
            ->returns('u');

        $query = $qb->getQuery();

        return $query->getResultSet();
    }

    private function getNodeId($userId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User{qnoow_id: {id}})')
            ->setParameter('id', (integer)$userId)
            ->returns('id(u) as id')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User with id ' . $userId . ' not found');
        }

        $id = $result->current()->offsetGet('id');

        return $id;
    }
}
