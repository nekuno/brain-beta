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

    protected function validateInvitationId($invitationId, $desired = true)
    {
        $errors = array('invitationId' => $this->existenceValidator->validateInvitationId($invitationId, $desired));

        $this->throwException($errors);
    }

    protected function validateInvitationToken($token, $excludedId = null, $desired = true)
    {
        if (!is_string($token) && !is_numeric($token)) {
            $this->throwException(array('token' => array('Token must be a string or a numeric')));
        }

        $errors = array('invitationToken' => $this->existenceValidator->validateInvitationToken($token, $excludedId, $desired));

        $this->throwException($errors);
    }

    protected function validateGroupId($groupId, $desired = true)
    {
        if (!is_int($groupId)) {
            $errors = array('groupId' => array('Group Id must be an integer'));
        } else {
            $errors = array('groupId' => $this->existenceValidator->validateGroupId($groupId, $desired));
        }

        $this->throwException($errors);
    }

    protected function validateGroupInData(array $data, $groupIdRequired = true)
    {
        if ($groupIdRequired && !isset($data['groupId'])) {
            $this->throwException(array('groupId', 'Group id is required for this action'));
        }

        if (isset($data['groupId'])) {
            $this->validateGroupId($data['groupId']);
        }
    }
}