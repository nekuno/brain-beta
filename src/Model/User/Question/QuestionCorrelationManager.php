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
        //Size fixed at 4 questions / set
        list ($questions, $minimum) = $this->findUncorrelatedFourGroup($correlations);

        return array(
            'totalCorrelation' => $minimum,
            'questions' => $questions
        );
    }

    public function getCorrelatedQuestions($preselected = 50, $minimumCountPerAnswer = 20)
    {
        $correlations = $this->getAllCorrelations($preselected);
        var_dump($this->countCorrelations($correlations));
        $correlations = $this->filterCorrelationsByMinimumAnswers($correlations, $minimumCountPerAnswer);
        var_dump($this->countCorrelations($correlations));
        //Size fixed at 2 for list
//        list ($questions, $maximum) = $this->findCorrelatedTwoGroup($correlations);

        return $correlations;
    }

    protected function countCorrelations($correlations)
    {
        $count = 0;
        foreach ($correlations as $q)
        {
            $count += count($q);
        }

        return $count;
    }

    /**
     * @param $preselected
     * @return array
     */
    public function getAllCorrelations($preselected)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $n = (integer)$preselected;
        $parameters = array('preselected' => $n);
        $qb->setParameters($parameters);

        //Choose questions pairs with no repetition
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

        //Count users for each answer pair (for each two questions)
        $qb->match('(q1)<-[:IS_ANSWER_OF]-(a1:Answer)')
            ->match('(q2)<-[:IS_ANSWER_OF]-(a2:Answer)')
            ->optionalMatch('(a1)<-[:ANSWERS]-(u:User)-[:ANSWERS]->(a2)')
            ->with('id(q1) AS q1,id(q2) AS q2,id(a1) AS a1,id(a2) AS a2,count(distinct(u)) AS users')
            //Use standard deviation as a correlation measure
            ->with('q1, q2, sum(users) as totalUsers,  stdevp(users) AS std, (count(distinct(a1))+count(distinct(a2))) AS answers')
            ->where('totalUsers>0')
            //Normalizations to get it between 0 and 1
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

    protected function filterCorrelationsByMinimumAnswers(array $correlations, $minimumAnswers)
    {
        $filteredCorrelations = array();
        foreach ($correlations as $q1 => $c1) {
            foreach ($c1 as $q2 => $correlation) {
                $isSharedEnough = $this->isSharedEnough($q1, $q2, $minimumAnswers);
                if ($isSharedEnough) {
                    $filteredCorrelations[$q1][$q2] = $correlation;
                }
            }
        }
        return $filteredCorrelations;
    }

    protected function isSharedEnough($question1Id, $question2Id, $minimumAnswers)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(question1:Question)')
            ->where('id(question1) = {question1Id}')
            ->setParameter('question1Id', (integer)$question1Id);
        $qb->match('(question2:Question)')
            ->where('id(question2) = {question2Id}')
            ->setParameter('question2Id', (integer)$question2Id);

        //calculates isValid for every answer combination
        $qb->match('(question1)<-[:IS_ANSWER_OF]-(a1:Answer)')
            ->match('(question2)<-[:IS_ANSWER_OF]-(a2:Answer)');
        $qb->match('(a1)<-[:ANSWERS]-(u:User)-[:ANSWERS]->(a2)')
            ->with('a1', 'a2', 'CASE WHEN count(u) >= {minimumAnswers} THEN 1 ELSE 0 END AS isValid')
            ->setParameter('minimumAnswers', (integer)$minimumAnswers);

        //boolean logic: one zero (invalid) makes areAllValid equal to zero
        $qb->with('collect(isValid) AS areValid')
            ->returns('reduce( valid = 1, eachValid in areValid | valid * eachValid) AS areAllValid');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $result->current()->offsetGet('areAllValid');
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
        foreach ($unsortedCorrelations as $q1 => $c1) {
            foreach ($c1 as $q2 => $correlation) {
                $correlations[10000 * $correlation] = array($q1, $q2);
            }
        }

        ksort($correlations);

        return $correlations;
    }
}