<?php

namespace Model\User\Question;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Paginator\PaginatedInterface;

class UserAnswerPaginatedModel implements PaginatedInterface
{

    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var AnswerManager
     */
    protected $am;

    protected $questionModel;

    /**
     * @param GraphManager $gm
     * @param \Model\User\Question\AnswerManager $am
     * @param QuestionModel $questionModel
     */
    public function __construct(GraphManager $gm, AnswerManager $am, QuestionModel $questionModel)
    {
        $this->gm = $gm;
        $this->am = $am;
        $this->questionModel = $questionModel;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        $hasId = isset($filters['id']);
        $hasLocale = isset($filters['locale']);

        return $hasId && $hasLocale;
    }

    /**
     * Slices the query according to $offset, and $limit
     * @param array $filters
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function slice(array $filters, $offset, $limit)
    {
        $userId = (integer)$filters['id'];
        $locale = $filters['locale'];

        $qb = $this->gm->createQueryBuilder();
        $qb
            ->match('(u:User {qnoow_id: { userId }})')
            ->setParameter('userId', $userId)
            ->match('(u)-[ua:ANSWERS]-(a:Answer)-[:IS_ANSWER_OF]-(q:Question)')
            ->where("EXISTS(q.text_$locale)")
            ->optionalMatch('(answers:Answer)-[:IS_ANSWER_OF]-(q)')
            ->optionalMatch('(u)-[:ACCEPTS]-(acceptedAnswers:Answer)-[:IS_ANSWER_OF]-(q)')
            ->optionalMatch('(u)-[r:RATES]-(q)')
            ->with('a', 'ua', 'q', 'acceptedAnswers', 'r', 'answers')
            ->orderBy('ID(answers)', 'ID(acceptedAnswers)')
            ->with('a', 'ua', 'q', 'COLLECT(DISTINCT acceptedAnswers) AS acceptedAnswers', 'r', 'COLLECT(DISTINCT answers) AS answers')
            ->returns('a AS answer', 'ua AS userAnswer', 'acceptedAnswers', 'q AS question', 'r AS rates', 'answers')
            ->orderBy('id(q)')
            ->skip('{ offset }')
            ->setParameter('offset', (integer)$offset)
            ->limit('{ limit }')
            ->setParameter('limit', (integer)$limit);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $response = array();
        /* @var $row Row */
        foreach ($result as $row) {

            $answerData = $this->am->build($row, $locale);

            $questionData = $answerData['question'];
            $questionId = $questionData['questionId'];
            $registerModes = $this->questionModel->getRegisterModes($questionId);
            $questionData['registerModes'] = $registerModes;

            $answerData['question'] = $questionData;

            $response[] = $answerData;
        }

        return $response;
    }

    /**
     * Counts the total results from queryset.
     * @param array $filters
     * @throws \Exception
     * @return int
     */
    public function countTotal(array $filters)
    {
        $id = (integer)$filters['id'];
        $locale = $filters['locale'];

        $qb = $this->gm->createQueryBuilder();
        $qb
            ->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', $id)
            ->match('(u)-[:ANSWERS]-(answer:Answer)-[:IS_ANSWER_OF]-(question:Question)')
            ->where("EXISTS(answer.text_$locale)")
            ->returns('COUNT(DISTINCT question) AS total');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return $row->offsetGet('total');
    }
}