<?php

namespace Model\User;

use Paginator\PaginatedInterface;

class RelationsPaginatedModel extends RelationsModel implements PaginatedInterface
{
    public function validateFilters(array $filters)
    {
        $isRelationshipOk = isset($filters['relation']) && in_array($filters['relation'], $this::getRelations());

        return $isRelationshipOk;
    }

    public function slice(array $filters, $offset, $limit)
    {
        list ($relation, $from, $to) = $this->getParameters($filters);

        $qb = $this->matchRelationshipQuery($relation, $from, $to)
            ->skip((integer)$offset)
            ->limit((integer)$limit);

        $this->returnRelationshipQuery($qb);

        $result = $qb->getQuery()->getResultSet();

        return $this->buildMany($result);
    }

    public function countTotal(array $filters)
    {
        list ($relation, $from, $to) = $this->getParameters($filters);

        return $this->count($relation, $from, $to);
    }

    protected function getParameters(array $filters)
    {
        $relation = $filters['relation'];
        $to = isset($filters['to']) ? $filters['to'] : null;
        $from = isset($filters['from']) ? $filters['from'] : null;

        return array($relation, $from, $to);
    }
}