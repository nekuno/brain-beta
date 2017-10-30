<?php

namespace Service\Validator;

use Service\MetadataService;
use Model\Neo4j\GraphManager;

class FilterUsersValidator extends Validator
{
    protected $metadataService;

    public function __construct(GraphManager $graphManager, MetadataService $metadataService, array $metadata)
    {
        parent::__construct($graphManager, $metadata);

        $this->metadataService = $metadataService;
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
        $choices = array();
        if (!empty($data) && $userId) {
            $groupChoices = $this->metadataService->getGroupChoices($userId);
            foreach ($groupChoices as $key => $value)
            {
                $groupChoices[$key] = $key;
            }
            $choices = array('groups' => $groupChoices);
        }
        $this->validateMetadata($data, $this->metadata, $choices);
    }
}