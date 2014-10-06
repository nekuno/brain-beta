<?php

namespace Model\Questionnaire;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Cypher\Query;

class QuestionModel
{

    protected $client;

    public function __construct(Client $client)
    {

        $this->client = $client;
    }

    /**
     * @param $userId
     * @param bool $sortByRating
     */
    public function getNextByUser($userId, $sortByRating = true)
    {

        $data = array(
            'userId' => $userId
        );

        $template = "MATCH (u:User)-[:ANSWERS]->(a:Answer)-[:IS_ANSWER_OF]->(q:Question)"
            . " WHERE u.qnoow_id = {userId}"
            . " WITH u, q"
            . " OPTIONAL MATCH (u)-[:SKIPS]->(q1), (:User)-[:REPORTS]->(q2)"
            . " WITH u AS user, collect(q) + collect(q1) + collect(q2) AS excluded"
            . " OPTIONAL MATCH (q3:Question)"
            . " WHERE NOT q3 IN excluded"
            . " WITH q3 AS next, excluded"
            . " OPTIONAL MATCH (u2:User)-[r:RATES]->(next)<-[:IS_ANSWER_OF]-(a2:Answer)"
            . " RETURN next, collect(DISTINCT a2) as nextAnswers, sum(r.rating) AS nextRating";

        if ($sortByRating && $this->sortByRating()) {
            $template .= " ORDER BY nextRating DESC";
        } else {
            $template .= " ORDER BY next.timestamp DESC";
        }

        $template .= " LIMIT 1;";

        $query = new Query(
            $this->client,
            $template,
            $data
        );

        foreach ($query->getResultSet() as $row) {
            return $row;
        }
    }

    /**
     * @return bool
     */
    public function sortByRating()
    {

        $rand = rand(1, 10);
        if ($rand !== 10) {
            return true;
        }

        return false;
    }

    /**
     * @param array $data
     * @return \Everyman\Neo4j\Query\ResultSet
     */
    public function create(array $data)
    {

        $template = "MATCH (u:User)"
            . " WHERE u.qnoow_id = {userId}"
            . " CREATE (q:Question)<-[c:CREATED_BY]-(u)"
            . " SET q.text = {text}, q.timestamp = timestamp(), c.timestamp = timestamp()"
            . " FOREACH (text in {answers}| CREATE (a:Answer {text: text})-[:IS_ANSWER_OF]->(q))";

        $template .= ";";

        //Create the Neo4j query object
        $query = new  Query(
            $this->client,
            $template,
            $data
        );

        foreach ($query->getResultSet() as $row) {
            return $row;
        }
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function skip(array $data)
    {

        $template = "MATCH"
            . " (q:Question)"
            . ", (u:User)"
            . " WHERE u.qnoow_id = {userId} AND id(q) = {questionId}"
            . " CREATE UNIQUE (u)-[r:SKIPS]->(q)"
            . " SET r.timestamp = timestamp()"
            . " RETURN r";

        $query = new Query($this->client, $template, $data);

        foreach ($query->getResultSet() as $row) {
            return $row;
        }
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function report(array $data)
    {

        $template = "MATCH"
            . " (q:Question)"
            . ", (u:User)"
            . " WHERE u.qnoow_id = {userId} AND id(q) = {questionId}"
            . " CREATE UNIQUE (u)-[r:REPORTS]->(q)"
            . " SET r.reason = {reason}, r.timestamp = timestamp()"
            . " RETURN r";

        $query = new Query($this->client, $template, $data);

        foreach ($query->getResultSet() as $row) {
            return $row;
        }
    }
}
