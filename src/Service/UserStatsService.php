<?php

namespace Service;

use Model\User\Content\ContentPaginatedModel;
use Model\User\Group\GroupModel;
use Model\User\RelationsModel;
use Model\User\Stats\UserStatsManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserStatsService
{
    /**
     * @var UserStatsManager
     */
    protected $userStatsManager;

    /**
     * @var RelationsModel
     */
    protected $relationsModel;

    /**
     * @var GroupModel
     */
    protected $groupModel;

    protected $contentPaginatedModel;

    function __construct(
        UserStatsManager $userStatsManager,
        GroupModel $groupModel,
        RelationsModel $relationsModel,
        ContentPaginatedModel $contentPaginatedModel)
    {
        $this->groupModel = $groupModel;
        $this->relationsModel = $relationsModel;
        $this->contentPaginatedModel = $contentPaginatedModel;
    }

    public function getStats($id)
    {
        return $this->userStatsManager->getStats($id);

    }

    public function getComparedStats($id, $otherUserId)
    {
        if (null === $otherUserId) {
            throw new NotFoundHttpException('User not found');
        }

        if ($id === $otherUserId) {
            throw new \InvalidArgumentException('Cannot get compared stats between an user and themselves');
        }

        return $this->userStatsManager->getComparedStats($id, $otherUserId);
    }
}