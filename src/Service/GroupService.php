<?php

namespace Service;

use Model\User\Filters\FilterUsersManager;
use Model\User\Group\Group;
use Model\User\Group\GroupModel;
use Service\Validator\ValidatorFactory;

class GroupService
{
    protected $groupModel;
    protected $filterUsersManager;
    /**
     * @var Validator\GroupValidator
     */
    protected $groupValidator;

    /**
     * GroupService constructor.
     * @param GroupModel $groupModel
     * @param FilterUsersManager $filterUsersManager
     * @param ValidatorFactory $validatorFactory
     */
    public function __construct(GroupModel $groupModel, FilterUsersManager $filterUsersManager, ValidatorFactory $validatorFactory)
    {
        $this->groupModel = $groupModel;
        $this->filterUsersManager = $filterUsersManager;
        $this->groupValidator = $validatorFactory->build('groups');
    }

    public function createGroup($groupData)
    {
        $this->validateOnCreate($groupData);
        $group = $this->groupModel->create($groupData);

        $this->updateFilterUsers($group, $groupData);

        return $group;
    }

    public function updateGroup($groupId, $groupData)
    {
        $this->validateOnUpdate($groupData, $groupId);
        $group = $this->groupModel->update($groupId, $groupData);
        $this->updateFilterUsers($group, $groupData);

        return $group;
    }

    public function addUser($groupId, $userId)
    {
        $this->validateOnAddUser($groupId, $userId);
        return $this->groupModel->addUser($groupId, $userId);
    }

    public function addGhostUser($groupId, $userId)
    {
        $this->validateOnAddUser($groupId, $userId);
        return $this->groupModel->addGhostUser($groupId, $userId);
    }

    public function removeUser($groupId, $userId)
    {
        $this->validateOnDelete($groupId, $userId);
        return $this->groupModel->removeUser($groupId, $userId);
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

    public function validateOnCreate(array $data)
    {
        $this->groupValidator->validateOnCreate($data);
    }

    public function validateOnUpdate(array $data, $groupId)
    {
        $data['groupId'] = $groupId;
        $this->groupValidator->validateOnUpdate($data);
    }

    public function validateOnDelete($groupId, $userId)
    {
        $data = array('groupId' => $groupId, 'userId' => $userId);
        $this->groupValidator->validateOnDelete($data);
    }

    protected function validateOnAddUser($groupId, $userId)
    {
        $data = array('groupId' => $groupId, 'userId' => $userId);
        $this->groupValidator->validateOnAddUser($data);
    }

}