<?php

namespace Model\User\Question;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;

class QuestionCorrelationManager
{
    protected $graphManager;

    /**
     * QuestionCorrelationManager constructor.
     * @param $graphManager
     */
    public function __construct(GraphManager $graphManager)
    {
        $this->graphManager = $graphManager;
    }

    /**
     * @param $preselected integer how many questions are preselected by rating to be analyzed
     * @return array
     */
    public function getUncorrelatedQuestions($preselected = 50)
    {
        $correlations = $this->getAllCorrelations($preselected);
        $correctCorrelations = $this->includeDefaultCorrelations($correlations, 1);
        //Size fixed at 4 questions / set
        list ($questions, $minimum) = $this->findUncorrelatedFourGroup($correctCorrelations);

        return array(
            'totalCorrelation' => $minimum,
            'questions' => $questions
        );
    }

    public function getCorrelatedQuestions($preselected = 50)
    {
        $correlations = $this->getAllCorrelations($preselected);

        $correctCorrelations = $this->includeDefaultCorrelations($correlations, 0);

        //Size fixed at 4 questions / set
        list ($questions, $maximum) = $this->findCorrelatedTwoGroup($correctCorrelations);

        return array(
            'totalCorrelation' => $maximum,
            'questions' => $questions
        );
    }

    public function getAllCorrelations($preselected)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $n = (integer)$preselected;
        $parameters = array('preselected' => $n);
        $qb->setParameters($parameters);

        $qb->match('(q:Question)')
            ->with('q')
            ->orderBy('q.ranking DESC')
            ->limit('{preselected}')
            ->with('collect(q) AS questions')
            ->match('(q1:Question),(q2:Question)')
            ->where(
                '(q1 in questions) AND (q2 in questions)',
                'id(q1)<id(q2)'
            )
            ->with('q1,q2');

        $qb->match('(q1)<-[:IS_ANSWER_OF]-(a1:Answer)')
            ->match('(q2)<-[:IS_ANSWER_OF]-(a2:Answer)')
            ->optionalMatch('(a1)<-[:ANSWERS]-(u:User)-[:ANSWERS]->(a2)')
            ->with('id(q1) AS q1,id(q2) AS q2,id(a1) AS a1,id(a2) AS a2,count(distinct(u)) AS users')
            ->with('q1, q2, sum(users) as totalUsers,  stdevp(users) AS std, (count(distinct(a1))+count(distinct(a2))) AS answers')
            ->where('totalUsers>0')
            ->with('q1, q2, std/totalUsers as normstd, answers')
            ->with('q1,q2,normstd*sqrt(answers) as finalstd')
            ->returns('q1,q2,finalstd');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $correlations = array();
        /* @var $row Row */
        foreach ($result as $row) {
            $correlations[$row->offsetGet('q1')][$row->offsetGet('q2')] = $row->offsetGet('finalstd');
        }

        return $correlations;
    }

    public function setDivisiveQuestions(array $ids)
    {
        $questions = array();
        foreach ($ids as $id) {
            $questions[] = $this->setDivisiveQuestion($id);
        }

        return $questions;
    }

    public function setDivisiveQuestion($id)
    {

        $qb = $this->graphManager->createQueryBuilder();
        $parameters = array('questionId' => (integer)$id);
        $qb->setParameters($parameters);

        $qb->match('(q:Question)')
            ->where('id(q)={questionId}')
            ->set('q :RegisterQuestion')
            ->returns('q');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return $row->offsetGet('q');
    }

    /**
     * @return integer
     * @throws \Exception
     */
    public function unsetDivisiveQuestions()
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(q:RegisterQuestion)')
            ->remove('q :RegisterQuestion')
            ->returns('count(q) AS c');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        return $row->offsetGet('c');
    }

    /**
     * @param $correlations
     * @param int $default
     * @return array
     */
    protected function includeDefaultCorrelations($correlations, $default = 1)
    {
        $correctCorrelations = array();
        foreach ($correlations as $q1 => $array) {
            foreach ($correlations as $q2 => $array2) {
                if (!($q1 < $q2)) {
                    continue;
                }
                $correctCorrelations[$q1][$q2] = isset($correlations[$q1][$q2]) ? $correlations[$q1][$q2] : $default;
            }
        }

        return $correctCorrelations;
    }

    protected function findUncorrelatedFourGroup($correlations)
    {
        $minimum = 600;
        $questions = array();
        foreach ($correlations as $q1 => $c1) {
            foreach ($correlations as $q2 => $c2) {
                foreach ($correlations as $q3 => $c3) {
                    foreach ($correlations as $q4 => $c4) {
                        if (!($q1 < $q2 && $q2 < $q3 && $q3 < $q4)) {
                            continue;
                        }
                        $foursome = $correlations[$q1][$q2] +
                            $correlations[$q2][$q3] +
                            $correlations[$q1][$q3] +
                            $correlations[$q1][$q4] +
                            $correlations[$q2][$q4] +
                            $correlations[$q3][$q4];
                        if ($foursome < $minimum) {
                            $minimum = $foursome;
                            $questions = array(
                                'q1' => $q1,
                                'q2' => $q2,
                                'q3' => $q3,
                                'q4' => $q4
                            );
                        }
                    }
                }
            }
        }

        return array($questions, $minimum);
    }

    protected function findCorrelatedTwoGroup($correlations)
    {
        $maximum = 0;
        $questions = array();
        foreach ($correlations as $q1 => $c1) {
            foreach ($correlations as $q2 => $correlation) {
                if (!($q1 < $q2)) {
                    continue;
                }
                $twosome = $correlation;
                if ($twosome > $maximum) {
                    $maximum = $twosome;
                    $questions = array(
                        'q1' => $q1,
                        'q2' => $q2,
                    );
                }
            }
        }

        return array($questions, $maximum);
    }

    public function sortCorrelations($unsortedCorrelations)
    {
        $correlations = array();
        foreach ($unsortedCorrelations as $q1 => $c1)
        {
            foreach ($c1 as $q2 => $correlation)
            {
                $correlations[10000*$correlation] = array($q1, $q2);
            }
        }

        ksort($correlations);
        return $correlations;
    }
}