<?php

namespace Service\Validator;

class ProfileValidator extends Validator
{
    public function validateProfile(array $data)
    {
        $metadata = $this->profileFilterModel->getProfileMetadata();

        if (isset($data['orientationRequired']) && $data['orientationRequired'] === false) {
            $metadata['orientation']['required'] = false;
        }

        return $this->validate($data, $metadata);
    }
}