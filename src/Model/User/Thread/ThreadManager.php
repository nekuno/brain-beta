<?php

namespace Model\User\Thread;

use Everyman\Neo4j\Label;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Manager\UserManager;
use Model\User;
use Model\User\GroupModel;
use Model\User\ProfileModel;
use Service\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThreadManager
{
    const LABEL_THREAD = 'Thread';
    const LABEL_THREAD_USERS = 'ThreadUsers';
    const LABEL_THREAD_CONTENT = 'ThreadContent';
    const SCENARIO_DEFAULT = 'default';

    static public $scenarios = array(ThreadManager::SCENARIO_DEFAULT);

    /** @var  GraphManager */
    protected $graphManager;
    /** @var  UserManager */
    protected $userManager;
    /** @var  UsersThreadManager */
    protected $usersThreadManager;
    /** @var  ContentThreadManager */
    protected $contentThreadManager;
    /** @var ProfileModel */
    protected $profileModel;
    /** @var  GroupModel */
    protected $groupModel;
    /** @var Validator */
    protected $validator;

    /**
     * ThreadManager constructor.
     * @param GraphManager $graphManager
     * @param UserManager $userManager
     * @param UsersThreadManager $um
     * @param ContentThreadManager $cm
     * @param ProfileModel $profileModel
     * @param GroupModel $groupModel
     * @param Validator $validator
     */
    public function __construct(GraphManager $graphManager, UserManager $userManager, UsersThreadManager $um,
                                ContentThreadManager $cm, ProfileModel $profileModel, GroupModel $groupModel,
                                Validator $validator)
    {
        $this->graphManager = $graphManager;
        $this->userManager = $userManager;
        $this->usersThreadManager = $um;
        $this->contentThreadManager = $cm;
        $this->profileModel = $profileModel;
        $this->groupModel = $groupModel;
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
            ->where('id(thread) = {id}')
            ->returns('thread');
        $qb->setParameter('id', (integer)$id);
        $result = $qb->getQuery()->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('Thread with id ' . $id . ' not found');
        }

        /** @var Node $threadNode */
        $threadNode = $result->current()->offsetGet('thread');

        return $this->buildThread($threadNode);
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
        $thread->setTotalResults($threadNode->getProperty('totalResults'));
        $thread->setCreatedAt($threadNode->getProperty('createdAt'));
        $thread->setUpdatedAt($threadNode->getProperty('updatedAt'));

        /* @var $label Label */
        foreach ($threadNode->getLabels() as $label){
            if ($label->getName() == 'ThreadDefault'){
                $thread->setDefault(true);
            }
        }

        return $thread;
    }

    public function getDefaultThreads(User $user, $scenario = ThreadManager::SCENARIO_DEFAULT)
    {
        $profile = $this->profileModel->getById($user->getId());

        $location = $profile['location'];

        $birthday = new \Datetime($profile['birthday']);
        $ageRangeMax = new \DateInterval('P5Y');
        $ageRangeMin = new \DateInterval('P5Y');
        $ageRangeMin->invert = 1;

        $genderDesired = $this->getDesiredFromProfile($profile);
        $nounDesired = $genderDesired == 'female'? 'Chicas' : 'Chicos';

        $threads = array(
            'default' => array(
                array(
                    'name' => $nounDesired. ' de '.$location['locality'],
                    'category' => ThreadManager::LABEL_THREAD_USERS,
                    'filters' => array(
                        'profileFilters' => array(
                            'birthday' => array(
                                'min' => $birthday->add($ageRangeMin)->format('Y-m-d'),
                                'max' => $birthday->add($ageRangeMax)->format('Y-m-d'),
                            ),
                            'location' => array(
                                'distance' => 50,
                                'location' => $location
                            ),
                            'gender' => $genderDesired,
                        ),
                        'order' => 'content',
                    ),
                    'default' => true,
                ),
                array(
                    'name' => 'Spotify a medida',
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'type' => array('Audio')
                    ),
                    'default' => true,
                ),
                array(
                    'name' => 'Vídeos de YouTube',
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'type' => array('Video')
                    ),
                    'default' => true,
                ),
                array(
                    'name' => 'GIF adictivos',
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'type' => array('Image')
                    ),
                    'default' => true,
                ),
                array(
                    'name' => 'Lo mejor de '.$location['locality'],
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'filters' => array(
                        'tag' => $location['locality'],
                    ),
                    'default' => true,
                ),
                array(
                    'name' => 'Canales de Twitter increíbles',
                    'category' => ThreadManager::LABEL_THREAD_CONTENT,
                    'default' => true,
                )
            )
        );

        foreach ($this->groupModel->getAllByUserId($user->getId()) as $group){
            $threads['default'][] = array(
                'name' => 'Gente de '.$group['name'],
                'category' => ThreadManager::LABEL_THREAD_USERS,
                'filters' => array(
                    'userFilters' => array(
                        'groups' => array($group['id']),
                    )
                ),
                'default' => true,
            );
        }

        if (!isset($threads[$scenario])) {
            return null;
        }

        return $threads[$scenario];
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
     * Creates an appropriate neo4j node from a set of filters
     * @param $userId
     * @param $data
     * @return Thread|null
     * @throws \Model\Neo4j\Neo4jException
     */
    public function create($userId, $data)
    {
        $this->validateEditThread($data, $userId);

        $name = isset($data['name']) ? $data['name'] : null;
        $category = isset($data['category']) ? $data['category'] : null;

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User{qnoow_id:{userId}})')
            ->create('(thread:' . ThreadManager::LABEL_THREAD . ':' . $category . ')')
            ->set('thread.name = {name}',
                'thread.createdAt = timestamp()',
                'thread.updatedAt = timestamp()')
            ->create('(u)-[:HAS_THREAD]->(thread)');
        if (isset($data['default']) && $data['default'] === true){
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

    public function update($threadId, $data)
    {
        $this->validateEditThread($data);

        $name = isset($data['name']) ? $data['name'] : null;
        $category = isset($data['category']) ? $data['category'] : null;

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(thread:Thread)')
            ->where('id(thread) = {id}')
            ->remove('thread:' . $this::LABEL_THREAD_USERS . ':' . $this::LABEL_THREAD_CONTENT)
            ->set('thread:' . $category)
            ->set('thread.name = {name}',
                'thread.updatedAt = timestamp()');
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

        $threadNode = $result->current()->offsetGet('thread');
        $thread = $this->buildThread($threadNode);

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
                    $id = $item['content']['id'];
                    $qb->match('(l:Link)')
                        ->where("id(l) = {$id}")
                        ->merge('(thread)-[:RECOMMENDS]->(l)')
                        ->with('thread');
                    $parameters += array($id => $id);
                    break;
                case 'Model\User\Thread\UsersThread':
                    $id = $item['id'];
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

        /** @var Node $threadNode */
        $threadNode = $result->current()->offsetGet('thread');

        return $this->buildThread($threadNode);

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

    private function validateEditThread($data, $userId = null)
    {
        if ($userId){
            $this->validator->validateUserId($userId);
        }

        $this->validator->validateEditThread($data, $this->getChoices());

        if (isset($data['filters'])){
            $this->usersThreadManager->getFilterUsersManager()->validateFilterUsers($data['filters'], $userId);
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
            if ($label != ThreadManager::LABEL_THREAD) {
                return $label;
            }
        }

        return null;
    }

    private function updateFromFilters(Thread $thread, $data)
    {
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

    private function getChoices()
    {
        return array(
            'category' => array(
                ThreadManager::LABEL_THREAD_USERS,
                ThreadManager::LABEL_THREAD_CONTENT
            )
        );
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

    private function YearsToBirthday($years)
    {
        $now = new \DateTime();
        $birthday = $now->modify('-' . $years . ' years')->format('Y-m-d');

        return $birthday;
    }

    private function getDesiredFromProfile(array $profile)
    {
        if(!isset($profile['orientation'])){
            return null;
        }

        if ($profile['orientation'] == 'heterosexual'){
            return $profile['gender'] === 'male' ? 'female' : 'male';
        }

        if ($profile['orientation'] == 'homosexual'){
            return $profile['gender'] === 'male' ? 'male' : 'female';
        }

        return null;
    }
}

