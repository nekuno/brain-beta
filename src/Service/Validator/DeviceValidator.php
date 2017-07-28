<?php

namespace Service\Validator;

class DeviceValidator extends Validator
{
    public function validateOnCreate($data)
    {
        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);

        $this->validateRegistrationIdInData($data, false);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);

        $this->validateRegistrationIdInData($data, true);
    }
}