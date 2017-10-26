<?php

namespace Model\User\Question\Admin;

use Everyman\Neo4j\Query\Row;
use Model\User\Question\QuestionModel;
use Paginator\PaginatedInterface;

class QuestionsAdminPaginatedModel extends QuestionAdminManager implements PaginatedInterface
{
    public function validateFilters(array $filters)
    {
        return isset($filters['locale']);
    }

    public function slice(array $filters, $offset, $limit)
    {
        $order = $filters['order'] ? $filters['order'] : 'answered';
        $orderDir = $filters['orderDir'] ? $filters['orderDir'] : 'desc';

        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(q:Question)')
            ->optionalMatch('(q)<-[s:SKIPS]-(:User)')
            ->optionalMatch('(q)<-[r:RATES]-(:User)')
            ->with('q', 'count(s) AS skipped', 'count(r) AS answered')
            ->orderBy("$order $orderDir");

        if (!is_null($offset)) {
            $qb->skip($offset);
        }
        if (!is_null($limit)) {
            $qb->limit($limit);
        }

        $qb->optionalMatch('(q)<-[:IS_ANSWER_OF]-(a:Answer)')
            ->returns('q', 'skipped', 'answered', 'collect(a) AS answers')
            ->orderBy("$order $orderDir");

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $return = array();

        /** @var Row $row */
        foreach ($result as $row) {
            $return[] = $this->questionAdminBuilder->build($row);
        }

        return $return;
    }

    public function countTotal(array $filters)
    {
        $locale = $filters['locale'];

        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(q:Question)')
            ->where("EXISTS(q.text_$locale)");

        $qb->returns('count(q) AS amount');

        $result = $qb->getQuery()->getResultSet();

        return $result->current()->offsetGet('amount');
    }
}