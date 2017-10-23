<?php

namespace Model\Metadata;

class UserFilterMetadataManager extends MetadataManager
{
    /**
     * @param $userId
     * @return array
     */
    public function getUserOptions($userId)
    {
        $groups = $this->getGroupsIds($userId);

        $choices = array(
            'groups' => array(),
        );
        foreach ($groups as $group){
            $choices['groups'][$group] = $group;
        }
        
        return $choices;
    }

//TODO: Use groupModel->getAllByUserId when groupModel has not filterUsersManagers dependency (QS-982)
    private function getGroupsIds($userId)
    {

        $qb = $this->gm->createQueryBuilder();
        $qb ->match('(u:User{qnoow_id: { userId }})')
            ->match('(g:Group)<-[:BELONGS_TO]-(u)')
            ->setParameter('userId', $userId)
            ->returns('id(g) as group');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $return = array();
        foreach ($result as $row) {
            $return[] = $row->offsetGet('group');
        }

        return $return;
    }

    protected function modifyPublicField($publicField, $name, $values)
    {
        $publicField = parent::modifyPublicField($publicField, $name, $values);

        $publicField = $this->modifyCommonAttributes($publicField, $values);

        $publicField = $this->modifyByType($publicField, $name, $values);

        return $publicField;
    }

    protected function modifyCommonAttributes(array $publicField, $values)
    {
        $publicField['labelEdit'] = isset($values['labelEdit']) ? $this->getLocaleString($values['labelEdit']) : $publicField['label'];
        $publicField['required'] = isset($values['required']) ? $values['required'] : false;
        $publicField['editable'] = isset($values['editable']) ? $values['editable'] : true;
        $publicField['hidden'] = isset($values['hidden']) ? $values['hidden'] : false;

        return $publicField;
    }

    /**
     * @param $publicField
     * @param $name
     * @param $values
     * @return mixed
     */
    protected function modifyByType($publicField, $name, $values)
    {
        $choiceOptions = $this->profileOptionManager->getOptions();
        $topProfileTags = $this->profileOptionManager->getTopProfileTags($name);

        switch ($values['type']) {
            case 'choice':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                break;
            case 'double_choice':
            case 'double_multiple_choices':
            case 'choice_and_multiple_choices':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                $publicField = $this->addDoubleChoices($publicField, $values);
                break;
            case 'multiple_choices':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                $publicField['max_choices'] = isset($values['max_choices']) ? $values['max_choices'] : 999;
                $publicField['min_choices'] = isset($values['min_choices']) ? $values['min_choices'] : 0;
                break;
            case 'tags_and_choice':
                $publicField['choices'] = array();
                if (isset($values['choices'])) {
                    foreach ($values['choices'] as $choice => $description) {
                        $publicField['choices'][$choice] = $this->getLocaleString($description);
                    }
                }
                $publicField['top'] = $topProfileTags;
                break;
            case 'tags':
                $publicField['top'] = $topProfileTags;
                break;
            default:
                break;
        }

        return $publicField;
    }
}