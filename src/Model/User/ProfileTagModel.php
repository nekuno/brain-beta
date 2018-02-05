<?php

namespace Model\User;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Cypher\Query;
use Model\LanguageText\LanguageTextManager;
use Model\Neo4j\GraphManager;

class ProfileTagModel
{
    /**
     * @var \Everyman\Neo4j\Client
     */
    protected $client;

    protected $graphManager;

    protected $languageTextManager;

    /**
     * @param \Everyman\Neo4j\Client $client
     * @param GraphManager $graphManager
     * @param LanguageTextManager $languageTextManager
     */
    public function __construct(Client $client, GraphManager $graphManager, LanguageTextManager $languageTextManager)
    {
        $this->client = $client;
        $this->graphManager = $graphManager;
        $this->languageTextManager = $languageTextManager;
    }

    /**
     * @param int $limit
     * @return array[]
     * @throws \Exception
     */
    public function findAllOld($limit = 99999)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(tag:ProfileTag)--(:Profile)-[r]-(i:InterfaceLanguage)')
            ->with('tag', 'i.id AS locale', 'count(r) AS amount')
            ->returns('id(tag) AS id, tag.name AS name', 'locale', 'amount')
            ->limit((integer)$limit);

        $result = $qb->getQuery()->getResultSet();

        $tags = array();
        foreach ($result as $row)
        {
            $id = $row->offsetGet('id');
            $name = $row->offsetGet('name');
            $locale = $row->offsetGet('locale');
            $amount = $row->offsetGet('amount');
            $tags[] = array('id' => $id, 'name' => $name, 'locale' => $locale, 'amount' => $amount);
        }

        return $tags;
    }

    public function deleteName($tagId)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(tag:ProfileTag)')
            ->where('id(tag) = {tagId}')
            ->setParameter('tagId', (integer)$tagId)
            ->remove('tag.name');

        $qb->getQuery()->getResultSet();
    }

    /**
     * Get a list of recommended tag
     * @param $type
     * @param $startingWith
     * @param int $limit
     * @throws \Exception
     * @return array
     */
    public function getProfileTags($type, $startingWith = '', $limit = 0)
    {
        $response = array();

        $params = array();

        $startingWithQuery = '';
        if ($startingWith != '') {
            $params['tag'] = '(?i)' . $startingWith . '.*';
            $startingWithQuery = 'WHERE tag.name =~ {tag}';
        }

        $limitQuery = '';
        if ($limit != 0) {
            $params['limit'] = (integer)$limit;
            $limitQuery = ' LIMIT {limit}';
        }

        $query = "
            MATCH
            (tag:ProfileTag:" . ucfirst($type) . ")
        ";
        $query .= $startingWithQuery;
        $query .= "
            RETURN
            distinct tag.name as name
            ORDER BY
            tag.name
        ";
        $query .= $limitQuery;

        //Create the Neo4j query object
        $contentQuery = new Query(
            $this->client,
            $query,
            $params
        );

        //Execute query
        try {
            $result = $contentQuery->getResultSet();

            foreach ($result as $row) {
                $response['items'][] = array('name' => $row['name']);
            }

        } catch (\Exception $e) {
            throw $e;
        }

        return $response;
    }

    public function deleteTagRelationships($userId, $tagLabel, $tags)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$userId)
            ->with('profile');
//TODO: After this is called, delete text nodes
        foreach ($tags as $index=>$tag) {
            $tagName = $tag['name'];
            $qb->optionalMatch("(profile)<-[tagRel:TAGGED]-(tag:ProfileTag: $tagLabel )<-[:TEXT_OF]-(:TextLanguage {text:{tag$index}})")
                ->setParameter("tag$index", $tagName)
                ->delete('tagRel')
                ->with('profile');
        }

        $qb->returns('profile');

        $result = $qb->getQuery()->getResultSet();

        return $result->count();
    }

    public function addTags($userId, $locale, $tagLabel, $tags)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$userId)
            ->with('profile');

        foreach ($tags as $tag) {
            $tagName = $tag['name'];
            $tagId = isset($tag['googleGraphId']) ? $tag['googleGraphId'] : null;

//            $qb->merge('(tag:ProfileTag:' . $tagLabel . ' {name: "' . $tagName . '" })')
            $qb->merge("(tag:ProfileTag: $tagLabel )<-[:TEXT_OF]-(:TextLanguage {text: '$tagName', locale: '$locale'})");
            if ($tagId) {
                $qb->set("tag.googleGraphId = '$tagId'");
            }
            $qb->merge('(profile)<-[:TAGGED]-(tag)')
                ->with('profile');
        }

        $qb->returns('profile');

        $result = $qb->getQuery()->getResultSet();

        return $result->count();
    }

    public function setTagsAndChoice($userId, $locale, $fieldName, $tagLabel, $tags)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$userId)
            ->with('profile');

        //TODO: Call this->delete and then this method just create
        $qb->optionalMatch('(profile)<-[tagsAndChoiceOptionRel:TAGGED]-(:' . $tagLabel . ')')
            ->delete('tagsAndChoiceOptionRel');

        $savedTags = array();
        foreach ($tags as $index => $value) {
            //TODO: Check if index is unnecesary due to with('profile') erasing previous node names
            $tagValue = $value['tag'];
            $tagName = $tagValue['name'];
            $tagId = isset($tagValue['googleGraphId']) ? $tagValue['googleGraphId'] : null;
            if ($tagId) {
                $qb->set("tag.googleGraphId = '$tagId'");
            }
            if (in_array($tagValue, $savedTags)) {
                continue;
            }
            $choice = !is_null($value['choice']) ? $value['choice'] : '';
            $tagIndex = 'tag_' . $index;
            $tagParameter = $fieldName . '_' . $index;
            $choiceParameter = $fieldName . '_choice_' . $index;

            $qb->with('profile')
//                ->merge('(' . $tagLabel . ':ProfileTag:' . $tagLabel . ' {name: { ' . $tagParameter . ' }})')
                ->merge("($tagIndex :ProfileTag: $tagLabel )<-[:TEXT_OF]-( :TextLanguage {text: { $tagParameter }, locale: {localeTag$index}})")
                ->merge('(profile)<-[:TAGGED {detail: {' . $choiceParameter . '}}]-(' . $tagIndex . ')')
                ->setParameter($tagParameter, $tagName)
                ->setParameter('localeTag'.$index, $locale)
                ->setParameter($choiceParameter, $choice);
            $savedTags[] = $tagValue;
        }
        $query = $qb->getQuery();
        $query->getResultSet();
    }
} 