<?php

namespace Model\User;

use Manager\UserManager;
use Model\Neo4j\GraphManager;
use Paginator\PaginatedInterface;

class UserDisabledPaginatedModel implements PaginatedInterface
{
    protected $graphManager;

    protected $userManager;

    public function __construct(GraphManager $graphManager, UserManager $userManager)
    {
        $this->graphManager = $graphManager;
        $this->userManager = $userManager;
    }

    public function validateFilters(array $filters)
    {
        return true;
    }

    public function slice(array $filters, $offset, $limit)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:UserDisabled)');

        $qb->returns('u')
            ->orderBy('id(u)')
            ->skip('{offset}')
            ->setParameter('offset', (integer)$offset)
            ->limit('{limit}')
            ->setParameter('limit', (integer)$limit);

        $result = $qb->getQuery()->getResultSet();

        return $this->userManager->buildMany($result);
    }

    public function countTotal(array $filters)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:UserDisabled)');

        $qb->returns('count(u) AS count');

        $result = $qb->getQuery()->getResultSet();

        return $result->current()->offsetGet('count');
    }
}