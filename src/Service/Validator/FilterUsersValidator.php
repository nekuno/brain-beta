<?php

namespace Service\Validator;

use Model\Metadata\UserFilterMetadataManager;
use Model\Neo4j\GraphManager;
use Model\User\ProfileOptionManager;

class FilterUsersValidator extends Validator
{
    protected $userFilterMetadataManager;

    public function __construct(GraphManager $graphManager, UserFilterMetadataManager $userFilterMetadataManager, array $metadata)
    {
        parent::__construct($graphManager, $metadata);

        $this->userFilterMetadataManager = $userFilterMetadataManager;
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
        if (!empty($data) && $userId) {
            $metadata = $this->metadata;
            $choices = $this->userFilterMetadataManager->getUserOptions($userId);
            $this->validateMetadata($data, $metadata, $choices);
        }
    }
}