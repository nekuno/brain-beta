<?php

namespace Model\User\Thread;


use Model\User\Filters\FilterUsers;

class UsersThread extends Thread
{

    /** @var  FilterUsers */
    protected $filterUsers;

    /**
     * @return FilterUsers
     */
    public function getFilterUsers()
    {
        return $this->filterUsers;
    }

    /**
     * @param FilterUsers $filterUsers
     */
    public function setFilterUsers($filterUsers)
    {
        $this->filterUsers = $filterUsers;
    }

    function jsonSerialize()
    {
        $array = parent::jsonSerialize();

        $array += array(
            'category' => ThreadManager::LABEL_THREAD_USERS,
            'filters' => array('userFilters' => $this->getFilterUsers()),
        );

        return $array;
    }
}