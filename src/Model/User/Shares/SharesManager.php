<?php

namespace Model\User\Shares;

use Model\Neo4j\GraphManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SharesManager
{
    /**
     * @var GraphManager
     */
    protected $graphManager;

    function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    public function mergeShares($userId1, $userId2, Shares $shares)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u1:User{qnoow_id: {id1}})', '(u2:User{qnoow_id: {id2}})')
            ->setParameter('id1', (integer)$userId1)
            ->setParameter('id2', (integer) $userId2);

        $qb->merge('(u1)-[shares:SHARES_WITH]-(u2)');

        foreach ($shares->toArray() as $parameter => $value)
        {
            $qb->set("shares.$parameter = {$parameter}")
                ->setParameter($parameter, $value);
        }

        $qb->returns('id(shares)');

        $result = $qb->getQuery()->getResultSet();

        if ($result->count() === 0)
        {
            $errorMessage = sprintf('Trying to share with nonexistant user %d or %d', $userId1, $userId2);
            throw new NotFoundHttpException($errorMessage);
        }

        $sharesId = $result->offsetGet('shares');
        $shares->setId($sharesId);

        return $shares;
    }

    public function deleteShares($userId1, $userId2)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u1:User{qnoow_id: {id1}})', '(u2:User{qnoow_id: {id2}})')
            ->setParameter('id1', (integer)$userId1)
            ->setParameter('id2', (integer) $userId2);

        $qb->match('(u1)-[shares:SHARES_WITH]-(u2)');
        $qb->delete('shares');

        $result = $qb->getQuery()->getResultSet();

        return $result->count();
    }
}