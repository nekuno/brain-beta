<?php

namespace Model\User\Stats;

use Everyman\Neo4j\Query\Row;
use Model\Neo4j\GraphManager;
use Model\User\Content\ContentPaginatedModel;
use Model\User\Group\GroupModel;
use Model\User\RelationsModel;
use Model\User\Stats\UserStatsModel;
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

        $userStats = new UserStatsModel(
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


}