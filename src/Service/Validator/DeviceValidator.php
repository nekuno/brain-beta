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

    protected function validateRegistrationIdInData(array $data, $registrationIdRequired = true)
    {
        if ($registrationIdRequired && !isset($data['registrationId'])) {
            $this->throwException(array('registrationId', 'Registration id is required for this action'));
        }

        if (isset($data['registrationId'])) {
            $this->validateRegistrationId($data['registrationId']);
        }
    }

    protected function validateRegistrationId($registrationId, $desired = true)
    {
        $errors = array('registrationId' => $this->existenceValidator->validateRegistrationId($registrationId, $desired));

        $this->throwException($errors);
    }
}