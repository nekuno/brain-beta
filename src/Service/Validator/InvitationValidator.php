<?php

namespace Service\Validator;

class InvitationValidator extends Validator
{
    public function validateOnCreate($data)
    {
        if (isset($data['token'])){
            //TODO: Move to validateInvitationToken?
            $token = $data['token'];
            if (!is_string($token) && !is_numeric($token)) {
                $this->throwException(array('token' => array('Token must be a string or a numeric')));
            }

            $this->validateInvitationToken($data['token'], $data['invitationId'], false);
        }

        $metadata = $this->metadata;

        $this->validateGroupInData($data, false);

        if (isset($data['userId'])) {
            $this->validateUserId($data['userId']);
        }

        $this->validateMetadata($data, $metadata);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $metadata['invitationId']['required'] = true;

        $this->validateGroupInData($data, false);

        if (isset($data['userId'])) {
            $this->validateUserId($data['userId']);
        }

        $this->validateMetadata($data, $metadata);

        if (isset($data['invitationId'])){
            $this->validateInvitationId($data['invitationId'], true);
        }

        if (isset($data['token'])){
            $this->validateInvitationToken($data['token'], $data['invitationId'], false);
        }
    }

    public function validateOnDelete($data)
    {
        $this->validateGroupInData($data);
        $this->validateUserInData($data);
    }
}