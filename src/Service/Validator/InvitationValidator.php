<?php

namespace Service\Validator;

class InvitationValidator extends Validator
{
    public function validateOnCreate($data)
    {
        if (isset($data['token'])) {
            $this->validateInvitationToken($data['token'], null, false);
        }

        $this->validateGroupInData($data, false);
        $this->validateUserInData($data, false);

        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $metadata['invitationId']['required'] = true;

        $this->validateGroupInData($data, false);
        $this->validateUserInData($data, false);

        $this->validateMetadata($data, $metadata);

        if (isset($data['invitationId'])) {
            $this->validateInvitationId($data['invitationId'], true);
        }

        if (isset($data['token'])) {
            $this->validateInvitationToken($data['token'], $data['invitationId'], false);
        }
    }

    public function validateOnDelete($data)
    {
        $this->validateGroupInData($data);
        $this->validateUserInData($data);
    }
}