<?php

namespace Model\User\Content;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\User;

class ContentReportModel extends ContentPaginatedModel
{
    const NOT_INTERESTING = 'not interesting';
    const HARMFUL = 'harmful';
    const SPAM = 'spam';
    const OTHER = 'other';

    /**
     * Slices the query according to $offset, and $limit.
     * @param array $filters
     * @param int $offset
     * @param int $limit
     * @throws \Exception
     * @return array
     */
    public function slice(array $filters, $offset, $limit)
    {
        $qb = $this->gm->createQueryBuilder();
        $userId = $filters['id'];
        $types = isset($filters['type']) ? $filters['type'] : array();

        $qb->match("(u:User)-[r:REPORTS]->(content:Link)");
        if ($userId) {
            $qb->where("(u.qnoow_id = { userId })")
                ->with('u, r, content');
        }
        $qb->filterContentByType($types, 'content', array('u', 'r'));

        $qb->optionalMatch("(content)-[:TAGGED]->(tag:Tag)")
            ->optionalMatch("(content)-[:SYNONYMOUS]->(synonymousLink:Link)")
            ->returns("DISTINCT content, id(content) as id, 
            collect(distinct { user: u, report: r }) as reports, labels(content) as types, collect(distinct tag.name) as tags, COLLECT (DISTINCT synonymousLink) AS synonymous")
            ->orderBy("content.created DESC")
            ->skip("{ offset }")
            ->limit("{ limit }")
            ->setParameters(
                array(
                    'userId' => (integer)$userId,
                    'offset' => (integer)$offset,
                    'limit' => (integer)$limit,
                )
            );

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $response = $this->buildResponse($result);

        return $response;
    }

    /**
     * Get a list of reports for content
     * @param $contentId
     * @throws \Exception
     * @return array
     */
    public function getById($contentId)
    {
        $params = array('contentId' => (integer)$contentId,);

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User)-[r:REPORTS]->(content:Link)')
            ->where('id(content) = { contentId }')
            ->setParameters($params)
            ->optionalMatch("(content)-[:TAGGED]->(tag:Tag)")
            ->optionalMatch("(content)-[:SYNONYMOUS]->(synonymousLink:Link)")
            ->returns("DISTINCT content, id(content) as id, 
            collect(distinct { user: u, report: r }) as reports, labels(content) as types, collect(distinct tag.name) as tags, COLLECT (DISTINCT synonymousLink) AS synonymous");

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new ValidationException(array('report' => array('Content not found')));
        }

        return $this->buildResponse($result);
    }

    /**
     * Counts the total results from queryset.
     * @param array $filters
     * @throws \Exception
     * @return int
     */
    public function countTotal(array $filters)
    {
        $userId = $filters['id'];
        $types = isset($filters['type']) ? $filters['type'] : array();

        $qb = $this->gm->createQueryBuilder();
        $count = 0;

        $qb->match("(u:User)-[r:REPORTS]->(content:Link)");
        if ($userId) {
            $qb->where("(u.qnoow_id = { userId })")
                ->with('u, r, content');
        }
        $qb->filterContentByType($types, 'content', array('u', 'r'));

        $qb->returns("count(distinct content) as total")
            ->setParameters(
                array(
                    'userId' => (integer)$userId,
                )
            );

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        foreach ($result as $row) {
            $count = $row['total'];
        }

        return $count;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        $userId = isset($filters['id']) ? $filters['id'] : null;
        if ($userId) {
            $this->validator->validateUserId($userId);
        }

        return $this->validator->validateRecommendateContent($filters, $this->getChoices());
    }

    /**
     * @param $result
     * @param $id
     * @return array
     */
    protected function buildResponse($result, $id = null)
    {
        $response = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $content = new Interest();

            $content->setId($row['id']);
            $this->hydrateNodeProperties($content, $row);
            $this->hydrateSynonymous($content, $row);
            $this->hydrateTags($content, $row);
            $this->hydrateTypes($content, $row);

            $formattedReports = array();
            $reports = $row->offsetGet('reports');
            /** @var Row $report */
            foreach($reports as $report) {
                $userNode = $report->offsetGet('user');
                $reportRelationship = $report->offsetGet('report');
                $formattedReports[] = array(
                    'id' => $userNode->getProperty('qnoow_id'),
                    'username' => $userNode->getProperty('username'),
                    'reason' => $reportRelationship->getProperty('reason'),
                    'reasonText' => $reportRelationship->getProperty('reasonText'),
                    'created' => (int) ($reportRelationship->getProperty('created')/1000),
                );
            }

            $response[] = array(
                'content' => $content,
                'reports' => $formattedReports,
            );
        }

        return $response;
    }

    /**
     * Report a content
     * @param $userId
     * @param $contentId
     * @param $reason
     * @param $reasonText
     * @throws \Exception
     * @return array
     */
    public function report($userId, $contentId, $reason, $reasonText = null)
    {
        $this->validate($reason, $reasonText);

        $params = array(
            'userId' => (integer)$userId,
            'contentId' => (integer)$contentId,
            'reason' => $reason,
            'reasonText' => $reasonText,
        );

        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: { userId }})', '(content:Link)')
            ->where('id(content) = { contentId }')
            ->merge('(u)-[r:REPORTS]->(content)')
            ->set('r.reason = { reason }')
            ->set('r.reasonText = { reasonText }')
            ->set('r.created = timestamp()')
            ->setParameters($params)
            ->returns('content, r');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new ValidationException(array('report' => array('Content not found')));
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    protected function validate($reason, $reasonText)
    {
        $errors = array();
        if (!in_array($reason, $this->getValidReasons())) {
            $errors['reason'] = array(sprintf('%s is not a valid reason', $reason));
        }
        if (isset($reasonText) && !is_string($reasonText)) {
            $errors['reasonText'] = array(sprintf('%s is not a valid reason text', $reasonText));
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    protected function build(Row $row)
    {
        /* @var $report Node */
        $content = $row->offsetGet('content');
        /* @var $report Relationship */
        $report = $row->offsetGet('r');

        return array(
            'id' => $content->getId(),
            'reason' => $report->getProperty('reason'),
            'reasonText' => $report->getProperty('reasonText'),
        );
    }

    private function getValidReasons()
    {
        return array(
            self::NOT_INTERESTING,
            self::HARMFUL,
            self::SPAM,
            self::OTHER,
        );
    }
} 