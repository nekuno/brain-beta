<?php

namespace Service\Validator;

class GroupValidator extends Validator
{
    public function validateOnCreate($data)
    {
        return $this->validate($data);
    }

    public function validateOnUpdate($data)
    {
        $this->validateGroupInData($data);
        return $this->validate($data);
    }

    public function validateOnDelete($data)
    {
        $this->validateGroupInData($data);
        $this->validateUserInData($data);
    }

    public function validateOnAddUser($data)
    {
        $this->validateGroupInData($data);
        $this->validateUserInData($data);
    }

    protected function validate(array $data)
    {
        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);

        $errors = array();
        if (isset($data['followers']) && $data['followers']) {
            $errors += $this->validateBoolean($data['followers']);
            if (!isset($data['influencer_id'])) {
                $errors['influencer_id'] = array('"influencer_id" is required for followers groups');
            } elseif (!is_int($data['influencer_id'])) {
                $errors['influencer_id'] = array('"influencer_id" must be integer');
            }
            if (!isset($data['min_matching'])) {
                $errors['min_matching'] = array('"min_matching" is required for followers groups');
            } elseif (!is_int($data['min_matching'])) {
                $errors['min_matching'] = array('"min_matching" must be integer');
            }
            if (!isset($data['type_matching'])) {
                $errors['type_matching'] = array('"type_matching" is required for followers groups');
            } elseif ($data['type_matching'] !== 'similarity' && $data['type_matching'] !== 'compatibility') {
                $errors['type_matching'] = array('"type_matching" must be "similarity" or "compatibility"');
            }
        }

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