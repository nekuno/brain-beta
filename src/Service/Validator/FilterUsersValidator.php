<?php

namespace Service\Validator;

use Model\Metadata\UserFilterMetadataManager;
use Model\Neo4j\GraphManager;
use Model\User\ProfileOptionManager;

class FilterUsersValidator extends Validator
{

    /**
     * @var ProfileOptionManager
     */
    protected $profileOptionManager;

    /**
     * @var UserFilterMetadataManager
     */
    protected $userFilterModel;

    public function __construct(GraphManager $graphManager, ProfileOptionManager $profileOptionManager, UserFilterMetadataManager $userFilterModel, array $metadata)
    {
        parent::__construct($graphManager, $metadata);

        $this->profileOptionManager = $profileOptionManager;
        $this->userFilterModel = $userFilterModel;
    }

    public function validateOnUpdate($data, $userId = null)
    {
        $this->validate($data, $userId);
    }

    public function validateOnCreate($data, $userId = null)
    {
        $this->validate($data, $userId);
    }

    protected function validate($data, $userId = null)
    {
        if (isset($data['userFilters']) && $userId) {
            $metadata = $this->metadata['user_filter'];
            $choices = $this->getUserChoices($userId);
            $this->validateMetadata($data['userFilters'], $metadata, $choices);
        }
        if (isset($data['profileFilters'])) {
            $metadata = $this->metadata['profile_filter'];
            $choices = $this->getProfileChoices();
            $this->validateMetadata($data['profileFilters'], $metadata, $choices);
        }
    }

    protected function getProfileChoices()
    {
        return $this->profileOptionManager->getChoiceOptionIds();
    }

    protected function getUserChoices($userId)
    {
        return $this->userFilterModel->getChoiceOptionIds($userId);
    }

}