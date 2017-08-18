<?php

namespace Service\Validator;

use Model\User\Token\TokensModel;

class TokenValidator extends Validator
{
    public function validateOnCreate($data)
    {
        $metadata = $this->metadata;
        $metadata['resourceId']['required'] = true;
        $choices = $this->getChoices();

        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, false);
        $this->validateTokenResourceId($data, false);

//        $this->validateExtraFields($data, $metadata);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();

        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, true);
        $this->validateTokenResourceId($data, true);

//        $this->validateExtraFields($data, $metadata);
    }

    public function validateOnDelete($data)
    {
        $metadata = $this->metadata;
        $metadata['oauthToken']['required'] = false;
        $metadata['resourceId']['required'] = false;
        $choices = $this->getChoices();

        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, true);
        $this->validateTokenResourceId($data, true);
    }

    private function getChoices()
    {
        return array(
            'resourceOwner' => TokensModel::getResourceOwners(),
        );
    }

    protected function validateTokenResourceId($data, $desired = true)
    {
        $userId = isset($data['userId']) ? $data['userId'] : null;
        $resourceOwner = $data['resourceOwner'];
        $resourceId = $data['resourceId'];
        $errors = array('tokenResourceId' => $this->existenceValidator->validateTokenResourceId($resourceId, $userId, $resourceOwner, $desired));

        $this->throwException($errors);
    }
}