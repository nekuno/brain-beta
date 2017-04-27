<?php

namespace Model\User\Content;

use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Everyman\Neo4j\Relationship;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;

class ContentReportModel
{
    const NOT_INTERESTING = 'not interesting';
    const HARMFUL = 'harmful';
    const SPAM = 'spam';
    const OTHER = 'other';

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @param GraphManager $gm
     */
    public function __construct(GraphManager $gm)
    {
        $this->gm = $gm;
    }

    /**
     * Get a list of recommended tag
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