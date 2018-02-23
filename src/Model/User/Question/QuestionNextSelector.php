<?php

namespace Model\User\Question;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionNextSelector
{
    protected $graphManager;

    /**
     * @param $graphManager
     */
    public function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    /**
     * @param $userId
     * @param $locale
     * @param bool $sortByRanking
     * @return bool|Row
     * @throws \Exception
     */
    public function getNextByUser($userId, $locale, $sortByRanking = true)
    {
        $divisiveQuestion = $this->getNextDivisiveQuestionByUserId($userId, $locale);

        if ($divisiveQuestion) {
            return $divisiveQuestion;
        }

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(user:User {qnoow_id: { userId }})')
            ->setParameter('userId', (int)$userId)
            ->optionalMatch('(user)-[:ANSWERS]->(a:Answer)-[:IS_ANSWER_OF]->(answered:Question)')
            ->optionalMatch('(user)-[:SKIPS]->(skip:Question)')
            ->optionalMatch('(user)-[:REPORTS]->(report:Question)')
            ->with('user', 'collect(answered) + collect(skip) + collect(report) AS excluded')
            ->match('(q3:Question)<-[:IS_ANSWER_OF]-(a2:Answer)')
            ->where('NOT q3 IN excluded', "EXISTS(q3.text_$locale)")
            ->with('q3 AS question', 'a2')
            ->orderBy('id(a2)')
            ->with('question', 'collect(DISTINCT a2) AS answers')
            ->returns('question', 'answers')
            ->orderBy($sortByRanking && $this->sortByRanking() ? 'question.ranking DESC' : 'question.timestamp ASC')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Question not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $row;
    }

    /**
     * @param $userId
     * @param $otherUserId
     * @param $locale
     * @param bool $sortByRanking
     * @return bool|Row
     * @throws \Exception
     */
    public function getNextByOtherUser($userId, $otherUserId, $locale, $sortByRanking = true)
    {
        $divisiveQuestion = $this->getNextDivisiveQuestionByUserId($userId, $locale);

        if ($divisiveQuestion) {
            return $divisiveQuestion;
        }

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(user:User {qnoow_id: { userId }}), (otherUser:User {qnoow_id: { otherUserId }})')
            ->match('(otherUser)-[:ANSWERS]->(a:Answer)-[:IS_ANSWER_OF]->(answeredOther:Question)')
            ->setParameters(
                array(
                    'userId' => (int)$userId,
                    'otherUserId' => (int)$otherUserId,
                )
            )
            ->optionalMatch('(user)-[:ANSWERS]->(:Answer)-[:IS_ANSWER_OF]->(answered:Question)')
            ->optionalMatch('(user)-[:REPORTS]->(report:Question)')
            ->with('user', 'a', 'collect(answered) + collect(report) AS excluded')
            ->match('(q:Question)<-[:IS_ANSWER_OF]-(a)')
            ->match('(q)<-[:IS_ANSWER_OF]-(allAnswers:Answer)')
            ->where("NOT q IN excluded", "EXISTS(q.text_$locale)")
            ->with('q AS question', 'allAnswers')
            ->orderBy('id(allAnswers)')
            ->with('question', 'collect(DISTINCT allAnswers) AS answers')
            ->returns('question', 'answers')
            ->orderBy($sortByRanking && $this->sortByRanking() ? 'question.ranking DESC' : 'question.timestamp ASC')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Question not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $row;
    }

    protected function getNextDivisiveQuestionByUserId($id, $locale)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(user:User {qnoow_id: { userId }})')
            ->setParameter('userId', (int)$id)
            ->optionalMatch('(user)-[:ANSWERS]->(a:Answer)-[:IS_ANSWER_OF]->(answered:RegisterQuestion)')
            ->with('user', 'collect(answered) AS excluded')
            ->match('(q3:RegisterQuestion)<-[:IS_ANSWER_OF]-(a2:Answer)')
            ->where('NOT q3 IN excluded', "EXISTS(q3.text_$locale)")
            ->with('q3 AS question', 'a2')
            ->orderBy('id(a2)')
            ->with('question', 'collect(DISTINCT a2) AS answers')
            ->returns('question', 'answers')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if (count($result) === 1) {
            /* @var $divisiveQuestions Row */
            $row = $result->current();

            return $row;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function sortByRanking()
    {

        $rand = rand(1, 10);
        if ($rand !== 10) {
            return true;
        }

        return false;
    }
}