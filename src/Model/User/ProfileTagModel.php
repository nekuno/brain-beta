<?php

namespace Model\User;

use Everyman\Neo4j\Client;
use Everyman\Neo4j\Cypher\Query;
use Model\LanguageText\LanguageTextManager;
use Model\Metadata\MetadataUtilities;
use Model\Neo4j\GraphManager;

class ProfileTagModel
{
    /**
     * @var \Everyman\Neo4j\Client
     */
    protected $client;

    protected $graphManager;

    protected $languageTextManager;

    protected $metadataUtilities;

    /**
     * @param \Everyman\Neo4j\Client $client
     * @param GraphManager $graphManager
     * @param LanguageTextManager $languageTextManager
     * @param MetadataUtilities $metadataUtilities
     */
    public function __construct(Client $client, GraphManager $graphManager, LanguageTextManager $languageTextManager, MetadataUtilities $metadataUtilities)
    {
        $this->client = $client;
        $this->graphManager = $graphManager;
        $this->languageTextManager = $languageTextManager;
        $this->metadataUtilities = $metadataUtilities;
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

        foreach ($tags as $tag) {
            $qb->optionalMatch('(profile)<-[tagRel:TAGGED]-(tag:' . $tagLabel . ' {name: "' . $tag . '" })')
                ->delete('tagRel')
                ->with('profile');
        }

        $result = $qb->getQuery()->getResultSet();

        return $result->count();
    }

    public function addTags($userId, $locale, $tagLabel, $tags)
    {
        $localeLabel = $this->languageTextManager->localeToLabel($locale);

        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$userId)
            ->with('profile');

        foreach ($tags as $tag) {
            $tagName = $tag['name'];
            $tagId = isset($tag['googleGraphId']) ? $tag['googleGraphId'] : null;

//            $qb->merge('(tag:ProfileTag:' . $tagLabel . ' {name: "' . $tagName . '" })')
            $qb->merge("(tag:ProfileTag: $tagLabel )<-[TEXT_OF]-( : $localeLabel {text: $tagName})");
            if ($tagId) {
                $qb->set("tag.googleGraphId = '$tagId'");
            }
            $qb->merge('(profile)<-[:TAGGED]-(tag)')
                ->with('profile');
        }

        $result = $qb->getQuery()->getResultSet();

        return $result->count();
    }

    public function setTagsAndChoice($userId, $locale, $fieldName, $tagLabel, $tags)
    {
        $localeLabel = $this->languageTextManager->localeToLabel($locale);

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
            //TODO: Move this case to different method/type
            //TODO: Check if index is unnecesary due to with('profile') erasing previous node names
            $tagValue = $fieldName === 'language' ?
                $this->metadataUtilities->getLanguageFromTag($value['tag']) :
                $value['tag'];
            $tagName = $tagValue['name'];
            $tagId = isset($tagValue['googleGraphId']) ? $tagValue['googleGraphId'] : null;
            if ($tagId) {
                $qb->set("tag.googleGraphId = '$tagId'");
            }
            if (in_array($tagValue, $savedTags)) {
                continue;
            }
            $choice = !is_null($value['choice']) ? $value['choice'] : '';
            $tagLabel = 'tag_' . $index;
            $tagParameter = $fieldName . '_' . $index;
            $choiceParameter = $fieldName . '_choice_' . $index;

            $qb->with('profile')
//                ->merge('(' . $tagLabel . ':ProfileTag:' . $tagLabel . ' {name: { ' . $tagParameter . ' }})')
                ->merge("($tagLabel :ProfileTag: $tagLabel )<-[TEXT_OF]-( $localeLabel {text: { $tagParameter }})")
                ->merge('(profile)<-[:TAGGED {detail: {' . $choiceParameter . '}}]-(' . $tagLabel . ')')
                ->setParameter($tagParameter, $tagValue)
                ->setParameter($choiceParameter, $choice);
            $savedTags[] = $tagValue;
        }
        $query = $qb->getQuery();
        $query->getResultSet();
    }
} 