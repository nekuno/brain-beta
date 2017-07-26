<?php

namespace Service\Validator;

class ProfileValidator extends Validator
{
    public function validateOnCreate($data)
    {
        return $this->validate($data);
    }

    public function validateOnUpdate($data)
    {
        return $this->validate($data);
    }

    public function validateOnDelete($userId)
    {
        $this->validateUserId($userId);
    }

    protected function validate(array $data)
    {
        $this->validateUserInData($data);

        $metadata = $this->metadataManagerFactory->build('profile')->getMetadata();
        $this->fixOrientationRequired($data, $metadata);

        return $this->validateMetadata($data, $metadata);
    }

    protected function fixOrientationRequired($data, &$metadata)
    {
        $isOrientationRequiredFalse = isset($data['orientationRequired']) && $data['orientationRequired'] === false;
        $metadata['orientation']['required'] = !$isOrientationRequiredFalse;
    }
}