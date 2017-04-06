<?php

namespace Model\User\Token\TokenStatus;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\ResultSet;
use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\Neo4j\QueryBuilder;
use Service\Validator;

class TokenStatusManager
{
    protected $graphManager;

    protected $validator;

    /**
     * TokenStatusManager constructor.
     * @param $graphManager
     * @param $validator
     */
    public function __construct(GraphManager $graphManager, Validator $validator)
    {
        $this->graphManager = $graphManager;
        $this->validator = $validator;
    }

    /**
     * @param $userId
     * @param $resource
     * @param $fetched
     * @return TokenStatus
     */
    public function setFetched($userId, $resource, $fetched)
    {
        return $this->setBooleanParameter($userId, $resource, 'fetched', $fetched);
    }

    /**
     * @param $userId
     * @param $resource
     * @param $processed
     * @return TokenStatus
     */
    public function setProcessed($userId, $resource, $processed)
    {
        return $this->setBooleanParameter($userId, $resource, 'processed', $processed);
    }

    protected function setBooleanParameter($userId, $resource, $name, $value)
    {
        $this->validate($value);

        $qb = $this->mergeTokenStatusQuery($userId, $resource);
        $this->setParameterQuery($qb, $name, (integer)$value);
        $this->setUpdateTimeQuery($qb);
        $this->returnStatusQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        $tokenStatus = $this->buildOne($result->current());

        return $tokenStatus;
    }

    /**
     * @param $userId
     * @param null $resource
     * @return TokenStatus[]
     */
    public function get($userId, $resource = null)
    {
        $qb = $this->mergeTokenStatusQuery($userId, $resource);
        $this->returnStatusQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        $statuses = $this->buildMany($result);

        return $statuses;
    }

    public function remove($userId, $resource)
    {
        $qb = $this->mergeTokenStatusQuery($userId, $resource);

        $this->deleteStatusQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        $tokenStatus = $this->buildOne($result->current());

        return $tokenStatus;
    }

    protected function validate($fetched)
    {
        $this->validator->validateTokenStatus($fetched);
    }

    /**
     * @param $userId
     * @param $resource
     * @return \Model\Neo4j\QueryBuilder
     */
    protected function mergeTokenStatusQuery($userId, $resource)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User{qnoow_id: {userId}})<-[:TOKEN_OF]-(token:Token{resource: {resource}})')
            ->with('token')
            ->limit(1);
        $qb->setParameters(
            array(
                'userId' => (integer)$userId,
                'resource' => $resource
            )
        );
        $qb->merge('(token)<-[:STATUS_OF]-(status:TokenStatus')
            ->with('status')
            ->limit(1);

        return $qb;
    }

    protected function setUpdateTimeQuery(QueryBuilder $qb)
    {
        $qb->set('status.updatedAt = time()');
    }

    /**
     * @param QueryBuilder $qb
     * @param $name
     * @param $value
     * @internal param $fetched
     */
    protected function setParameterQuery(QueryBuilder $qb, $name, $value)
    {
        $qb->set("status.$name = {value}")
            ->setParameter('value', $value);
    }

    protected function deleteStatusQuery(QueryBuilder $qb)
    {
        $qb->detachDelete('(status)');
    }

    protected function returnStatusQuery(QueryBuilder $qb)
    {
        $qb->returns('status');
    }

    protected function buildOne(Row $row)
    {
        if (!$row->offsetExists('status') || $row->offsetGet('status') == null) {
            throw new \InvalidArgumentException('Token status not found');
        }

        /** @var Node $statusNode */
        $statusNode = $row->offsetGet('status');

        $tokenStatus = new TokenStatus();

        $tokenStatus->setFetched((integer)$statusNode->getProperty('fetched'));
        $tokenStatus->setProcessed((integer)$statusNode->getProperty('processed'));
        $tokenStatus->setUpdatedAt($statusNode->getProperty('updatedAt'));

        return $tokenStatus;
    }

    protected function buildMany(ResultSet $resultSet)
    {
        $statuses = array();
        foreach ($resultSet as $row) {
            $statuses = $this->buildOne($row);
        }

        return $statuses;
    }
}