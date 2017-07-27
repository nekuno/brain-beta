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

        $this->validateUserInData($data, true);
        $this->validateTokenResourceId($data['resourceId'], $data['userId'], $data['resourceOwner'], false);

        $this->validateExtraFields($data, $metadata);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $choices = $this->getChoices();

        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, true);
        $this->validateTokenResourceId($data['resourceId'], $data['userId'], $data['resourceOwner'], true);

        $this->validateExtraFields($data, $metadata);
    }

    public function validateOnDelete($data)
    {
        $metadata = $this->metadata;
        $metadata['oauthToken']['required'] = false;
        $metadata['resourceId']['required'] = false;
        $choices = $this->getChoices();

        $this->validateMetadata($data, $metadata, $choices);

        $this->validateUserInData($data, true);
        $this->validateTokenResourceId($data['resourceId'], $data['userId'], $data['resourceOwner'], true);
    }

    private function getChoices()
    {
        return array(
            'resourceOwner' => TokensModel::getResourceOwners(),
        );
    }
}