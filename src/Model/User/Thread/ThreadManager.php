<?php

namespace Model\User\Thread;

use Everyman\Neo4j\Label;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\User;
use Model\User\Group\Group;
use Model\User\ProfileModel;
use Service\Validator\ThreadValidator;
use Service\Validator\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\Translator;

class ThreadManager
{
    const LABEL_THREAD = 'Thread';
    const LABEL_THREAD_USERS = 'ThreadUsers';
    const LABEL_THREAD_CONTENT = 'ThreadContent';
    const SCENARIO_DEFAULT = 'default';
    const SCENARIO_DEFAULT_LITE = 'default_lite';
    const SCENARIO_NONE = 'none';

    static public $scenarios = array(ThreadManager::SCENARIO_DEFAULT, ThreadManager::SCENARIO_DEFAULT_LITE, ThreadManager::SCENARIO_NONE);

    /** @var  GraphManager */
    protected $graphManager;
    /** @var  UsersThreadManager */
    protected $usersThreadManager;
    /** @var  ContentThreadManager */
    protected $contentThreadManager;
    /** @var ProfileModel */
    protected $profileModel;
    /** @var Translator */
    protected $translator;
    /** @var ThreadValidator */
    protected $validator;

    /**
     * ThreadManager constructor.
     * @param GraphManager $graphManager
     * @param UsersThreadManager $um
     * @param ContentThreadManager $cm
     * @param ProfileModel $profileModel
     * @param Translator $translator
     * @param ThreadValidator $validator
     */
    public function __construct(
        GraphManager $graphManager,
        UsersThreadManager $um,
        ContentThreadManager $cm,
        ProfileModel $profileModel,
        Translator $translator,
        ThreadValidator $validator
    ) {
        $this->graphManager = $graphManager;
        $this->usersThreadManager = $um;
        $this->contentThreadManager = $cm;
        //TODO: Move profileModel and translator dependencies to a new Class DefaultThreadManager to create data
        $this->profileModel = $profileModel;
        $this->translator = $translator;
        $this->validator = $validator;
    }

    /**
     * @param $id
     * @return Thread
     * @throws \Exception
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getById($id)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}');
        $qb->optionalMatch('(thread)-[:IS_FROM_GROUP]->(group:Group)')
            ->returns('thread', 'id(group) AS groupId');
        $qb->setParameter('id', (integer)$id);
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('Thread with id ' . $id . ' not found');
        }

        return $this->build($result->current());
    }

    /**
     * @param $userId
     * @return Thread[]
     * @throws \Exception
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getByUser($userId)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id= {id}')
            ->optionalMatch('(user)-[:HAS_THREAD]->(thread:Thread)')
            ->returns('user, collect(thread) as threads');
        $qb->setParameter('id', (integer)$userId);
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User with id ' . $userId . ' not found');
        }

        /** @var Row $row */
        $row = $result->current();

        $threads = array();
        /** @var Node $threadNode */
        foreach ($row->offsetGet('threads') as $threadNode) {
            $threads[] = $this->buildThread($threadNode);
        }

        return $threads;
    }

    public function getByFilter($filterId)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(filter:Filter{filter:{filterId}})')
            ->with('filter')
            ->setParameter('filterId', (integer)$filterId);

        $qb->match('(thread:Thread)-[:HAS_FILTER]->(filter)')
            ->returns('thread');

        $result = $qb->getQuery()->getResultSet();

        $threadNode = $result->current()->offsetExists('thread') ? $result->offsetGet('thread') : null;

        return $this->buildThread($threadNode);
    }

    public function build(Row $row)
    {
        $threadNode = $row->offsetGet('thread');
        $thread = $this->buildThread($threadNode);

        if ($groupId = $row->offsetExists('groupId') ? $row->offsetGet('groupId') : null) {
            $thread->setGroupId($groupId);
        };

        return $thread;
    }

    /**
     * Builds a complete Thread object from a neo4j node
     * @param Node $threadNode
     * @return Thread
     * @throws \Exception
     */
    public function buildThread(Node $threadNode)
    {
        $id = $threadNode->getId();

        switch ($category = $this->getCategory($threadNode)) {
            case $this::LABEL_THREAD_USERS: {
                $thread = $this->usersThreadManager->buildUsersThread($id, $threadNode->getProperty('name'));
                $cached = $this->usersThreadManager->getCached($thread);
                break;
            }
            case $this::LABEL_THREAD_CONTENT: {
                $thread = $this->contentThreadManager->buildContentThread($id, $threadNode->getProperty('name'), $threadNode->getProperty('type'));
                $cached = $this->contentThreadManager->getCached($thread);
                break;
            }
            default :
                throw new \Exception('Thread category ' . $category . ' not found or supported');
        }

        $thread->setCached($cached);
        $thread->setRecommendationUrl($this->getRecommendationUrl($thread));
        $thread->setTotalResults($threadNode->getProperty('totalResults'));
        $thread->setCreatedAt($threadNode->getProperty('createdAt'));
        $thread->setUpdatedAt($threadNode->getProperty('updatedAt'));

        /* @var $label Label */
        foreach ($threadNode->getLabels() as $label) {
            if ($label->getName() == 'ThreadDefault') {
                $thread->setDefault(true);
            }
        }

        return $thread;
    }

    /**
     * @param User $user
     * @param string $scenario
     * @return array
     */
    public function getDefaultThreads(User $user, $scenario = ThreadManager::SCENARIO_DEFAULT)
    {
        try {
            $profile = $this->profileModel->getById($user->getId());
        } catch (NotFoundHttpException $e) {
            return array();
        }

        if (!isset($profile['location'])) {
            $profile['location'] = array(
                'latitude' => 40.4167754,
                'longitude' => -3.7037902,
                'address' => 'Madrid',
                'locality' => 'Madrid',
                'country' => 'Spain'
            );
        }

        if (!isset($profile['birthday'])) {
            $profile['birthday'] = '1970-01-01';
        }

        $locale = isset($profile['interfaceLanguage']) ? $profile['interfaceLanguage'] : 'es';

        $this->translator->setLocale($locale);

        $location = $profile['location'];

        $birthdayRange = $this->getAgeRangeFromProfile($profile);

        $genderDesired = $this->getDesiredFromProfile($profile);
        $nounDesired = $this->translator->trans('threads.default.' . $genderDesired);

        //specific order to be created from bottom to top
        $threads = array(
            ThreadManager::SCENARIO_DEFAULT => array(
                array(
                    'name' => $this->translator->trans('threads.default.twitter_channels'),
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'contentFilters' => array(
                            'type' => array('Creator', 'LinkTwitter'),
                        ),
                    ),
                    'default' => true,
                ),
                array(
                    'name' => str_replace('%location%', $location['locality'], $this->translator->trans('threads.default.best_of_location')),
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'contentFilters' => array(
                            'tags' => array($location['locality']),
                        ),
                    ),
                    'default' => true,
                ),
                array(
                    'name' => $this->translator->trans('threads.default.youtube_videos'),
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'contentFilters' => array(
                            'type' => array('Video')
                        ),
                    ),
                    'default' => true,
                ),
                array(
                    'name' => $this->translator->trans('threads.default.spotify_music'),
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'contentFilters' => array(
                            'type' => array('Audio')
                        ),
                    ),
                    'default' => true,
                ),
                array(
                    'name' => $this->translator->trans('threads.default.images'),
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'contentFilters' => array(
                            'type' => array('Image')
                        ),
                    ),
                    'default' => true,
                ),
                array(
                    'name' => str_replace(
                        array('%desired%', '%location%'),
                        array($nounDesired, $location['locality']),
                        $this->translator->trans('threads.default.desired_from_location')
                    ),
                    'category' => ThreadManager::LABEL_THREAD_USERS,
                    'filters' => array(
                        'userFilters' => array(
                            'birthday' => array(
                                'min' => $birthdayRange['min'],
                                'max' => $birthdayRange['max'],
                            ),
                            'location' => array(
                                'distance' => 50,
                                'location' => $location
                            ),
                            'descriptiveGender' => array($genderDesired !== 'people' ? $genderDesired : null),
                        ),
                        'order' => 'content',
                    ),
                    'default' => true,
                ),
            ),
            ThreadManager::SCENARIO_DEFAULT_LITE => array(
                array(
                    'name' => str_replace(
                        array('%desired%', '%location%'),
                        array($nounDesired, $location['locality']),
                        $this->translator->trans('threads.default.desired_from_location')
                    ),
                    'category' => ThreadManager::LABEL_THREAD_USERS,
                    'filters' => array(
                        'userFilters' => array(
                            'birthday' => array(
                                'min' => 18,
                                'max' => 80,
                            ),
                            'descriptiveGender' => array($genderDesired !== 'people' ? $genderDesired : null),
                        ),
                        'order' => 'content',
                    ),
                    'default' => true,
                ),
            ),
            ThreadManager::SCENARIO_NONE => array(

            ),
        );

        if (!isset($threads[$scenario])) {
            return array();
        }

        $this->fixDescriptiveGenderFilter($threads[$scenario]);
        return $threads[$scenario];
    }

    private function fixDescriptiveGenderFilter(&$threads) {
        foreach ($threads as &$thread) {
            if (isset($thread['filters']['userFilters']) && isset($thread['filters']['userFilters']['descriptiveGender']) && $thread['filters']['userFilters']['descriptiveGender'] == array(null)) {
                unset($thread['filters']['userFilters']['descriptiveGender']);
            }
        }
    }

    /**
     * @param $userId
     * @param array $threadsData
     * @return Thread[]
     */
    public function createBatchForUser($userId, array $threadsData)
    {
        $returnThreads = array();

        $existingThreads = $this->getByUser($userId);

        foreach ($threadsData as $threadData) {
            foreach ($existingThreads as $existingThread) {
                if ($threadData['name'] == $existingThread->getName()) {
                    continue 2;
                }
            }

            $returnThreads[] = $this->create($userId, $threadData);
        }

        return $returnThreads;
    }

    /**
     * Creates an appropriate neo4j node and links a filter node to it
     * @param $userId
     * @param $data
     * @return Thread|null
     * @throws \Model\Neo4j\Neo4jException
     */
    public function create($userId, $data)
    {
        $this->validateOnCreate($data, $userId);

        $name = isset($data['name']) ? $data['name'] : null;
        $category = isset($data['category']) ? $data['category'] : null;

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User{qnoow_id:{userId}})')
            ->create('(thread:' . ThreadManager::LABEL_THREAD . ':' . $category . ')')
            ->set(
                'thread.name = {name}',
                'thread.createdAt = timestamp()',
                'thread.updatedAt = timestamp()'
            )
            ->create('(u)-[:HAS_THREAD]->(thread)');
        if (isset($data['default']) && $data['default'] === true) {
            $qb->set('thread :ThreadDefault');
        }
        $qb->returns('id(thread) as id');
        $qb->setParameters(
            array(
                'name' => $name,
                'userId' => (integer)$userId
            )
        );

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            return null;
        }

        $id = $result->current()->offsetGet('id');
        $thread = $this->getById($id);

        return $this->updateFromFilters($thread, $data);
    }

    /**
     * Replaces thread data with $data
     * @param $threadId
     * @param $userId
     * @param $data
     * @return Thread|null
     * @throws \Exception
     * @throws \Model\Neo4j\Neo4jException
     */
    public function update($threadId, $userId, $data)
    {
        $this->validateOnUpdate($data, $userId);

        $name = isset($data['name']) ? $data['name'] : null;
        $category = isset($data['category']) ? $data['category'] : null;

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->remove('thread:' . $this::LABEL_THREAD_USERS . ':' . $this::LABEL_THREAD_CONTENT . ':ThreadDefault')
            ->set('thread:' . $category)
            ->set(
                'thread.name = {name}',
                'thread.updatedAt = timestamp()'
            );
        $qb->returns('thread');
        $qb->setParameters(
            array(
                'name' => $name,
                'id' => (integer)$threadId
            )
        );

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            return null;
        }

        $thread = $this->build($result->current());

        return $this->updateFromFilters($thread, $data);

    }

    /**
     * @param Thread $thread Which thread returned the results
     * @param array $items
     * @param $total
     * @return Thread
     * @throws \Exception
     * @throws \Model\Neo4j\Neo4jException
     */
    public function cacheResults(Thread $thread, array $items, $total)
    {
        $this->deleteCachedResults($thread);

        $parameters = array(
            'id' => $thread->getId(),
            'total' => (integer)$total,
        );
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->set('thread.totalResults = {total}')
            ->with('thread');

        foreach ($items as $item) {
            switch (get_class($thread)) {
                case 'Model\User\Thread\ContentThread':
                    /** @var $item User\Recommendation\ContentRecommendation */
                    $id = $item->getContent()['id'];
                    $qb->match('(l:Link)')
                        ->where("id(l) = {$id}")
                        ->merge('(thread)-[:RECOMMENDS]->(l)')
                        ->with('thread');
                    $parameters += array($id => $id);
                    break;
                case 'Model\User\Thread\UsersThread':
                    /** @var $item User\Recommendation\UserRecommendation */
                    $id = $item->getId();
                    $qb->match('(u:User)')
                        ->where("u.qnoow_id = {$id}")
                        ->merge('(thread)-[:RECOMMENDS]->(u)')
                        ->with('thread');
                    $parameters += array($id => $id);
                    break;
                default:
                    throw new \Exception('Thread ' . $thread->getId() . ' has a not valid category.');
                    break;
            }
        }

        $qb->returns('thread');
        $qb->setParameters($parameters);
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('Thread with id ' . $thread->getId() . ' not found');
        }

        return $this->build($result->current());
    }

    public function deleteById($id)
    {
        $thread = $this->getById($id);

        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->optionalMatch('(thread)-[r]-()')
            ->delete('r, thread')
            ->returns('count(r) as amount');
        $qb->setParameter('id', $thread->getId());
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('Thread with id ' . $id . ' not found');
        }

        return $result->current()->offsetGet('amount');
    }

    public function deleteGroupThreads($userId, $groupId)
    {
        $threads = $this->getByUser($userId);

        foreach ($threads as $thread) {

            if (!$thread instanceof UsersThread) {
                continue;
            }

            $filter = $thread->getFilterUsers();
            if (!$filter || !isset($filter->getUserFilters()['groups']) || !is_array($filter->getUserFilters()['groups'])) {
                continue;
            }

            /** @var Node $groupNode */
            foreach ($filter->getUserFilters()['groups'] as $groupNode) {
                if ($groupNode->getId() == $groupId) {
                    $this->deleteById($thread->getId());
                }
            }
        }
    }

    public function createGroupThread(Group $group, $userId)
    {
        $groupData = $this->getGroupThreadData($group, $userId);
        $thread = $this->create($userId, $groupData);
        $thread = $this->joinToGroup($thread, $group);

        return $thread;
    }

    private function joinToGroup(Thread $thread, Group $group)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(thread:Thread)')
            ->where('id(thread) = {threadId}')
            ->setParameter('threadId', $thread->getId());
        $qb->match('(group:Group)')
            ->where('id(group) = {groupId}')
            ->setParameter('groupId', $group->getId());
        $qb->set('thread:ThreadGroup');
        $qb->merge('(thread)-[:IS_FROM_GROUP]->(group)');
        $qb->returns('thread', 'id(group) AS groupId');
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException(sprintf('Thread with id %s or group with id %s not found', $thread->getId(), $group->getId()));
        }

        return $this->build($result->current());
    }

    public function getFromUserAndGroup(User $user, Group $group)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = {userId}')
            ->setParameter('userId', $user->getId());
        $qb->match('(group:Group)')
            ->where('id(group) = {groupId}')
            ->setParameter('groupId', $group->getId());

        $qb->match('(u)-[:HAS_THREAD]->(thread:Thread)-[:IS_FROM_GROUP]->(group)')
            ->returns('thread', 'id(group) AS groupId');

        $result = $qb->getQuery()->getResultSet();

        return $this->build($result->current());

    }

    public function getGroupThreadData(Group $group, $userId)
    {
        try {
            $profile = $this->profileModel->getById($userId);
        } catch (NotFoundHttpException $e) {
            return array();
        }

        $locale = isset($profile['interfaceLanguage']) ? $profile['interfaceLanguage'] : 'es';
        $this->translator->setLocale($locale);

        return array(
            'name' => str_replace('%group%', $group->getName(), $this->translator->trans('threads.default.people_from_group')),
            'category' => ThreadManager::LABEL_THREAD_USERS,
            'filters' => array(
                'userFilters' => array(
                    'groups' => array($group->getId()),
                )
            ),
            'default' => false,
        );
    }

    private function validateOnCreate($data, $userId)
    {
        $data['userId'] = $userId;
        $this->validator->validateOnCreate($data);
        if (isset($data['filters'])) {
            $this->usersThreadManager->getFilterUsersManager()->validateOnCreate($data['filters'], $userId);
        }
    }

    private function validateOnUpdate($data, $userId)
    {
        $data['userId'] = $userId;
        $this->validator->validateOnUpdate($data);
        if (isset($data['filters'])) {
            $this->usersThreadManager->getFilterUsersManager()->validateOnUpdate($data['filters'], $userId);
        }
    }

    /**
     * @param Node $threadNode
     * @return null|string
     */
    private function getCategory(Node $threadNode)
    {
        //$labels = $threadNode->getLabels();
        $id = $threadNode->getId();
        $qb = $this->graphManager->createQueryBuilder();
        $qb->setParameter('id', $id);

        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->returns('labels(thread) as labels');

        $rs = $qb->getQuery()->getResultSet();
        $labels = $rs->current()->offsetGet('labels');
        /** @var Label $label */
        foreach ($labels as $label) {
            if ($label != ThreadManager::LABEL_THREAD && $label != 'ThreadDefault') {
                return $label;
            }
        }

        return null;
    }

    private function updateFromFilters(Thread $thread, $data)
    {
        $this->deleteCachedResults($thread);
        $filters = isset($data['filters']) ? $data['filters'] : array();
        switch (get_class($thread)) {
            case 'Model\User\Thread\ContentThread':

                $this->contentThreadManager->update($thread->getId(), $filters);
                break;
            case 'Model\User\Thread\UsersThread':

                $this->usersThreadManager->update($thread->getId(), $filters);
                break;
            default:
                return null;
        }

        return $this->getById($thread->getId());
    }

    private function deleteCachedResults(Thread $thread)
    {
        $parameters = array(
            'id' => $thread->getId()
        );
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->optionalMatch('(thread)-[r:RECOMMENDS]->()')
            ->delete('r');
        $qb->setParameters($parameters);
        $qb->getQuery()->getResultSet();

    }

    private function getRecommendationUrl(Thread $thread)
    {
        return 'threads/' . $thread->getId() . '/recommendation?offset=20';
    }

    private function getDesiredFromProfile(array $profile)
    {
        //QS-1001: Changed for now
//        if (!isset($profile['orientation']) || !isset($profile['gender'])) {
//            return 'people';
//        }
//
//        if ($profile['orientation'] == 'heterosexual') {
//            return $profile['gender'] === 'male' ? 'female' : 'male';
//        }
//
//        if ($profile['orientation'] == 'homosexual') {
//            return $profile['gender'] === 'male' ? 'male' : 'female';
//        }
//
//        if ($profile['orientation'] == 'bisexual') {
//            return 'people';
//        }

        return 'people';
    }

    private function getAgeRangeFromProfile(array $profile)
    {
        $ageRangeMax = new \DateInterval('P5Y');
        $ageRangeMin = new \DateInterval('P5Y');
        $ageRangeMin->invert = 1;
        $rawAgeMin = (new \DateTime($profile['birthday']))->add($ageRangeMax)->diff(new \DateTime())->y;
        $rawAgeMax = (new \DateTime($profile['birthday']))->add($ageRangeMin)->diff(new \DateTime())->y;

        return array(
            'max' => $rawAgeMax <= 99 ? ($rawAgeMax >= 14 ? $rawAgeMax : 14) : 99,
            'min' => $rawAgeMin <= 99 ? ($rawAgeMin >= 14 ? $rawAgeMin : 14) : 99,
        );
    }
}

