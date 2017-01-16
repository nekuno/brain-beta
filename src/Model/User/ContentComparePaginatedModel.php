<?php

namespace Model\User;

use Everyman\Neo4j\Node;
use Model\LinkModel;
use Model\User\Token\TokensModel;
use Paginator\PaginatedInterface;
use Model\Neo4j\GraphManager;
use Service\Validator;

class ContentComparePaginatedModel implements PaginatedInterface
{
    /**
     * @var GraphManager
     */
    protected $gm;

    /**
     * @var TokensModel
     */
    protected $tokensModel;

    /**
     * @var LinkModel
     */
    protected $linkModel;

    /**
     * @var Validator
     */
    protected $validator;

    //TODO: Try to unify this with ContentCompareModel, maybe using from a superclass
    public function __construct(GraphManager $gm, TokensModel $tokensModel, LinkModel $linkModel, Validator $validator)
    {
        $this->gm = $gm;
        $this->tokensModel = $tokensModel;
        $this->linkModel = $linkModel;
        $this->validator = $validator;
    }

    /**
     * Hook point for validating the query.
     * @param array $filters
     * @return boolean
     */
    public function validateFilters(array $filters)
    {
        $userId = isset($filters['id'])? $filters['id'] : null;
        $this->validator->validateUserId($userId);

        return $this->validator->validateRecommendateContent($filters, $this->getChoices());
    }

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
        $response = array();
        $id = $filters['id'];
        $id2 = $filters['id2'];
        $types = isset($filters['type']) ? $filters['type'] : array();

        $qb = $this->gm->createQueryBuilder();

        $showOnlyCommon = false;
        if (isset($filters['showOnlyCommon'])) {
            $showOnlyCommon = $filters['showOnlyCommon'];
        }

        $qb->match("(u:User), (u2:User)")
            ->where("u.qnoow_id = { userId }","u2.qnoow_id = { userId2 }")
            ->match("(u)-[r:LIKES]->(content:Link {processed: 1})");
        $qb->filterContentByType($types, 'content', array('u2', 'r'));

        if (isset($filters['tag'])) {
            $names = json_encode($filters['tag']);
            $qb->match('(content)-[:TAGGED]->(filterTag:Tag)')
                ->where('filterTag.name IN { filterTags } ');

            $params['filterTags'] = $names;
        }
        if ($showOnlyCommon) {
            $qb->match("(u2)-[r2:LIKES]->(content)");
        } else {
            $qb->optionalMatch("(u2)-[r2:LIKES]->(content)");
        }

        $qb->optionalMatch("(content)-[:TAGGED]->(tag:Tag)")
            ->optionalMatch("(u2)-[a:AFFINITY]->(content)")
            ->optionalMatch("(content)-[:SYNONYMOUS]->(synonymousLink:Link)")
            ->returns("id(content) as id,  type(r) as rate1, type(r2) as rate2, content, a.affinity as affinity, collect(distinct tag.name) as tags, labels(content) as types, COLLECT (DISTINCT synonymousLink) AS synonymous")
            ->skip("{ offset }")
            ->limit("{ limit }")
            ->setParameters(
                array(
                    'userId' => $id,
                    'userId2' => $id2,
                    'tag' => isset($filters['tag']) ? $filters['tag'] : null,
                    'offset' => (integer)$offset,
                    'limit' => (integer)$limit,
                )
            );

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        //TODO: Build with linkModel
        foreach ($result as $row) {
            $content = array();

            $content['id'] = $row['id'];
            $content['url'] = $row['content']->getProperty('url');
            $content['title'] = $row['content']->getProperty('title');
            $content['description'] = $row['content']->getProperty('description');
            $content['thumbnail'] = $row['content']->getProperty('thumbnail');
            $content['synonymous'] = array();

            if (isset($row['synonymous'])) {
                foreach ($row['synonymous'] as $synonymousLink) {
                    /* @var $synonymousLink Node */
                    $synonymous = array();
                    $synonymous['id'] = $synonymousLink->getId();
                    $synonymous['url'] = $synonymousLink->getProperty('url');
                    $synonymous['title'] = $synonymousLink->getProperty('title');
                    $synonymous['thumbnail'] = $synonymousLink->getProperty('thumbnail');

                    $content['synonymous'][] = $synonymous;
                }
            }

            foreach ($row['tags'] as $tag) {
                $content['tags'][] = $tag;
            }

            foreach ($row['types'] as $type) {
                $content['types'][] = $type;
            }

            $user1 = array();
            $user1['user']['id'] = $id;
            $user1['rate'] = $row['rate1'];
            $content['user_rates'][] = $user1;

            if (null != $row['rate2']) {
                $user2 = array();
                $user2['user']['id'] = $id2;
                $user2['rate'] = $row['rate2'];
                $content['user_rates'][] = $user2;
            }

            if ($row['content']->getProperty('embed_type')) {
                $content['embed']['type'] = $row['content']->getProperty('embed_type');
                $content['embed']['id'] = $row['content']->getProperty('embed_id');
            }

            $content['match'] = $row['affinity'];

            $response[] = $content;
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
        $id = $filters['id'];
        $id2 = isset($filters['id2']) ? (integer)$filters['id2'] : null;
        $types = isset($filters['type']) ? $filters['type'] : array();

        $count = 0;
        $qb = $this->gm->createQueryBuilder();

        $showOnlyCommon = false;
        if (isset($filters['showOnlyCommon'])) {
            $showOnlyCommon = $filters['showOnlyCommon'];
        }

        $qb->match("(u:User)","(u2:User)")
            ->where("u.qnoow_id = { userId }", "u2.qnoow_id = { userId2 }")
            ->match("(u)-[r:LIKES]->(content:Link {processed: 1})");
        $qb->filterContentByType($types, 'content', array('u2', 'r'));

        if ($showOnlyCommon) {
            $qb->match("(u2)-[r2:LIKES]->(content)");
        }

        if (isset($filters['tag'])) {
            $qb->match('(content)-[:TAGGED]->(filterTag:Tag)')
                ->where('filterTag.name IN { filterTags } ');

            $params['filterTags'] = $filters['tag'];
        }

        $qb->returns("count(r) as total")
            ->setParameters(
                array(
                    'userId' => (integer)$id,
                    'userId2' => $id2,
                    'tag' => isset($filters['tag']) ? $filters['tag'] : null,

                )
            );

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        foreach ($result as $row) {
            $count = $row['total'];
        }

        return $count;
    }

    public function countAll($userId, $ownUserId, $showOnlyCommon = false)
    {
        $types = $this->linkModel->getValidTypes();
        $qb = $this->gm->createQueryBuilder();
        $qb->match("(u:User {qnoow_id: { userId }})")
            ->setParameter('userId', $userId);
        $with = 'u,';
        if ($showOnlyCommon) {
            $qb->with(trim($with, ','))
                ->match("(ownU:User {qnoow_id: { ownUserId }})")
                ->setParameter('ownUserId', $ownUserId);
            $with .= 'ownU,';
        }
        foreach ($types as $type) {
            $qb->optionalMatch("(u)-[:LIKES]->(content$type:$type {processed: 1})");
            if ($showOnlyCommon) {
                $qb->where("(ownU)-[:LIKES]->(content$type)");
            }
            $qb->with($with . "count(DISTINCT content$type) AS count$type");
            $with .= "count$type,";
        }

        $qb->returns(trim($with, ','));

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $totals = array();
        foreach ($result as $row) {
            foreach ($types as $type) {
                $totals[$type] = $row["count$type"];
            }
        }

        return $totals;
    }

    // TODO: Useful for filtering by social networks
    private function buildSocialNetworkCondition($userId, $relationship)
    {
        $tokens = $this->tokensModel->getAll($userId);
        $socialNetworks = array();
        foreach ($tokens as $token) {
            $socialNetworks[] = $token['resourceOwner'];
        }
        $whereSocialNetwork = array();
        foreach ($socialNetworks as $socialNetwork) {
            $whereSocialNetwork[] = "EXISTS ($relationship.$socialNetwork)";
        }

        return implode(' OR ', $whereSocialNetwork);
    }

    protected function getChoices()
    {
        return array('type' => $this->linkModel->getValidTypes());
    }
}