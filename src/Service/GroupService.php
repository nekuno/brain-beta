<?php

namespace Service;

use Model\User\Filters\FilterUsersManager;
use Model\User\Group\Group;
use Model\User\Group\GroupModel;

class GroupService
{
    protected $groupModel;
    protected $filterUsersManager;

    /**
     * GroupService constructor.
     * @param $groupModel
     * @param $filterUsersManager
     */
    public function __construct(GroupModel $groupModel, FilterUsersManager $filterUsersManager)
    {
        $this->groupModel = $groupModel;
        $this->filterUsersManager = $filterUsersManager;
    }

    public function createGroup($groupData)
    {
        $group = $this->groupModel->create($groupData);
        $this->updateFilterUsers($group, $groupData);

        return $group;
    }

    public function updateGroup($groupId, $groupData)
    {
        $group = $this->groupModel->update($groupId, $groupData);
        $this->updateFilterUsers($group, $groupData);

        return $group;
    }

    private function updateFilterUsers(Group $group, array $data)
    {
        if (isset($data['followers'])) {
            $filterUsers = $this->filterUsersManager->updateFilterUsersByGroupId(
                $group->getId(),
                array(
                    'userFilters' => array(
                        $data['type_matching'] => $data['min_matching']
                    )
                )
            );
            $group->setFilterUsers($filterUsers);
        }
    }

}