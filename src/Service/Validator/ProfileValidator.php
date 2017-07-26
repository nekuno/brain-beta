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

    public function validateOnDelete($data)
    {
        $this->validateUserInData($data);
    }

    protected function validate(array $data)
    {
        $this->validateUserInData($data);

        $metadata = $this->metadata;
        $this->fixOrientationRequired($data, $metadata);

        return $this->validateMetadata($data, $metadata);
    }

    protected function fixOrientationRequired($data, &$metadata)
    {
        $isOrientationRequiredFalse = isset($data['orientationRequired']) && $data['orientationRequired'] === false;
        $metadata['orientation']['required'] = !$isOrientationRequiredFalse;
    }
}