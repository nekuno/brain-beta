<?php

namespace Model\User\Device;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Service\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeviceModel
{

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var string
     */
    protected $applicationServerKey;

    /**
     * @var Validator
     */
    protected $validator;

    public function __construct(GraphManager $gm, $applicationServerKey, $validator)
    {
        $this->gm = $gm;
        $this->applicationServerKey = $applicationServerKey;
        $this->validator = $validator;
    }

    public function getAll($userId)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User {qnoow_id: { userId }})-[:HAS_DEVICE]->(d:Device)')
            ->setParameter('userId', $userId)
            ->returns('u', 'd')
            ->orderBy('d.createdAt DESC');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $return = array();

        foreach ($result as $row) {
            $return[] = $this->build($row);
        }

        return $return;
    }

    public function exists($endpoint)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(d:Device)')
            ->where('d.endpoint = { endpoint }')
            ->setParameter('endpoint', $endpoint)
            ->returns('d');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) > 0) {
            return true;
        }

        return false;
    }

    public function getByToken($token)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)-[:HAS_DEVICE]->(d:Device)')
            ->where('d.token = { token }')
            ->setParameter('token', $token)
            ->returns('u', 'd');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Device not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    public function getByEndpoint($endpoint)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)-[:HAS_DEVICE]->(d:Device)')
            ->where('d.endpoint = { endpoint }')
            ->setParameter('endpoint', $endpoint)
            ->returns('u', 'd');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Device not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    public function create(array $data)
    {
        $this->validate($data);

        if ($this->exists($data['endpoint'])) {
            throw new ValidationException(array('device' => array(sprintf('Device with endpoint "%s" already exists', $data['endpoint']))));
        }

        $qb = $this->gm->createQueryBuilder();

        $qb->create('(d:Device)')
            ->set('d.key = { key }')
            ->set('d.endpoint = { endpoint }')
            ->set('d.token = { token }')
            ->set('d.platform = { platform }')
            ->set('d.createdAt = timestamp()')
            ->with('d')
            ->match('(u:User {qnoow_id: { userId }})')
            ->createUnique('(u)-[:HAS_DEVICE]->(d)')
            ->setParameters(array(
                'key' =>  isset($data['key']) ? $data['key'] : null,
                'userId' => (int)$data['userId'],
                'endpoint' => $data['endpoint'],
                'token' => isset($data['token']) ? $data['token'] : null,
                'platform' => $data['platform'],
            ))
            ->returns('u', 'd');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        $device = $this->build($row);

        return $device;
    }

    public function update(array $data)
    {
        $this->validate($data);

        if (!$this->exists($data['endpoint'])) {
            throw new ValidationException(array('device' => array(sprintf('Device with endpoint "%s" does not exist', $data['endpoint']))));
        }

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(d:Device)<-[:HAS_DEVICE]-(u:User {qnoow_id: { userId }})')
            ->where('d.endpoint = { endpoint }')
            ->set('d.key = { key }')
            ->set('d.token = { token }')
            ->set('d.platform = { platform }')
            ->set('d.updatedAt = timestamp()')
            ->with('d', 'u')
            ->setParameters(array(
                'key' =>  isset($data['key']) ? $data['key'] : null,
                'userId' => (int)$data['userId'],
                'endpoint' => $data['endpoint'],
                'token' => isset($data['token']) ? $data['token'] : null,
                'platform' => $data['platform'],
            ))
            ->returns('u', 'd');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        $device = $this->build($row);

        return $device;
    }

    public function delete(array $data)
    {
        $this->validate($data);
        $device = $this->getByEndpoint($data['endpoint']);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)<-[r:HAS_DEVICE]-(d:Device)')
            ->where('d.endpoint = { endpoint }')
            ->setParameter('endpoint', $data['endpoint'])
            ->delete('r', 'd')
            ->returns('u');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Device not found');
        }

        return $device;

    }

    public function validate(array $data)
    {
        $this->validator->validateDevice($data);
    }

    /**
     * @param Row $row
     * @return Device
     */
    protected function build(Row $row)
    {
        /* @var $node Node */
        $node = $row->offsetGet('d');

        /* @var $userNode Node */
        $userNode = $row->offsetGet('u');

        $device = new Device();
        $device->setKey($node->getProperty('key'));
        $device->setEndpoint($node->getProperty('endpoint'));
        $device->setUserId($userNode->getProperty('qnoow_id'));
        $device->setToken($node->getProperty('token'));
        $device->setPlatform($node->getProperty('platform'));
        $device->setCreatedAt($node->getProperty('createdAt'));
        $device->setUpdatedAt($node->getProperty('updatedAt'));

        return $device;
    }

}