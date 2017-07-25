<?php

namespace Model\Metadata;

class ProfileMetadataManager extends MetadataManager
{
    protected function modifyPublicField($publicField, $name, $values)
    {
        $publicField['labelEdit'] = isset($values['labelEdit']) ? $this->getLocaleString($values['labelEdit']) : $publicField['label'];
        $publicField['required'] = isset($values['required']) ? $values['required'] : false;
        $publicField['editable'] = isset($values['editable']) ? $values['editable'] : true;

        switch ($values['type']) {
            case 'tags_and_choice':
                if (isset($values['choices'])) {
                    foreach ($values['choices'] as $name => $choices) {
                        $publicField['choices'][$name] = $name;
                    }
                }
                break;
        }

        return $publicField;
    }

}