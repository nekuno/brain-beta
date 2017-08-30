<?php

namespace Model\User\Stats;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\User\Content\ContentPaginatedModel;
use Model\User\Group\Group;
use Model\User\Group\GroupModel;
use Model\User\RelationsModel;
use Model\User\Stats\UserStats;
use Model\User\UserComparedStats;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsManager
{
    /**
     * @var GraphManager
     */
    protected $graphManager;

    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    /**
     * @var GroupModel
     */
    protected $groupModel;

    protected $contentPaginatedModel;

    function __construct(GraphManager $graphManager,
                         GroupModel $groupModel,
                         RelationsModel $relationsModel,
                         ContentPaginatedModel $contentPaginatedModel)
    {
        $this->graphManager = $graphManager;
        $this->groupModel = $groupModel;
        $this->relationsModel = $relationsModel;
        $this->contentPaginatedModel = $contentPaginatedModel;
    }

    //TODO: If we can get this from respective managers, and not be slower, this would be UserStatsService
    public function getStats($id)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->match('(u:User {qnoow_id: { id }})')
            ->setParameter('id', (integer)$id)
            ->with('u')
            ->optionalMatch('(u)-[r:ANSWERS]->(:Answer)')
            ->returns('count(r) AS questionsAnswered', 'u.available_invitations AS available_invitations');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if ($result->count() < 1) {
            throw new NotFoundHttpException('User not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $numberOfReceivedLikes = $this->relationsModel->countTo($id, RelationsModel::LIKES);
        $numberOfUserLikes = $this->relationsModel->countFrom($id, RelationsModel::LIKES);

        $groups = $this->groupModel->getAllByUserId($id);

        $contentLikes = $this->contentPaginatedModel->countAll($id);

        $userStats = new UserStats(
            $contentLikes['Link'],
            $contentLikes['Video'],
            $contentLikes['Audio'],
            $contentLikes['Image'],
            (integer)$numberOfReceivedLikes,
            (integer)$numberOfUserLikes,
            $groups,
            $row->offsetGet('questionsAnswered'),
            $row->offsetGet('available_invitations')
        );

        return $userStats;

    }

    /**
     * @param $id1
     * @param $id2
     * @return UserComparedStats
     * @throws \Exception
     */
    public function getComparedStats($id1, $id2)
    {
        $qb = $this->graphManager->createQueryBuilder();

        $qb->setParameters(
            array(
                'id1' => (integer)$id1,
                'id2' => (integer)$id2
            )
        );

        $qb->match('(u:User {qnoow_id: { id1 }}), (u2:User {qnoow_id: { id2 }})')
            ->optionalMatch('(u)-[:BELONGS_TO]->(g:Group)<-[:BELONGS_TO]-(u2)')
            ->with('u', 'u2', 'collect(distinct g) AS groupsBelonged')
            ->optionalMatch('(u)-[:TOKEN_OF]-(token:Token)')
            ->with('u', 'u2', 'groupsBelonged', 'collect(distinct token.resourceOwner) as resourceOwners')
            ->optionalMatch('(u2)-[:TOKEN_OF]-(token2:Token)');
        $qb->with('u, u2', 'groupsBelonged', 'resourceOwners', 'collect(distinct token2.resourceOwner) as resourceOwners2')
            ->optionalMatch('(u)-[:LIKES]->(link:Link)')
            ->where('(u2)-[:LIKES]->(link)', 'link.processed = 1', 'NOT link:LinkDisabled')
            ->with('u', 'u2', 'groupsBelonged', 'resourceOwners', 'resourceOwners2', 'count(distinct(link)) AS commonContent')
            ->optionalMatch('(u)-[:ANSWERS]->(answer:Answer)')
            ->where('(u2)-[:ANSWERS]->(answer)')
            ->returns('groupsBelonged, resourceOwners, resourceOwners2, commonContent, count(distinct(answer)) as commonAnswers');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();

        $groups = array();
        foreach ($row->offsetGet('groupsBelonged') as $groupNode) {
            $groups[] = Group::createFromNode($groupNode);
        }

        $resourceOwners = array();
        foreach ($row->offsetGet('resourceOwners') as $resourceOwner) {
            $resourceOwners[] = $resourceOwner;
        }
        $resourceOwners2 = array();
        foreach ($row->offsetGet('resourceOwners2') as $resourceOwner2) {
            $resourceOwners2[] = $resourceOwner2;
        }

        $commonContent = $row->offsetGet('commonContent') ?: 0;
        $commonAnswers = $row->offsetGet('commonAnswers') ?: 0;

        $userStats = new UserComparedStats(
            $groups,
            $resourceOwners,
            $resourceOwners2,
            $commonContent,
            $commonAnswers
        );

        return $userStats;
    }


}