<?php

namespace Model\Metadata;

class UserFilterMetadataManager extends MetadataManager
{
    protected function modifyPublicField($publicField, $name, $values)
    {
        $publicField = parent::modifyPublicField($publicField, $name, $values);

        $publicField = $this->modifyCommonAttributes($publicField, $values);

        return $publicField;
    }

    protected function modifyCommonAttributes(array $publicField, $values)
    {
        $locale = $this->translator->getLocale();
        $publicField['labelEdit'] = isset($values['labelEdit']) ? $this->metadataUtilities->getLocaleString($values['labelEdit'], $locale) : $publicField['label'];
        $publicField['required'] = isset($values['required']) ? $values['required'] : false;
        $publicField['editable'] = isset($values['editable']) ? $values['editable'] : true;
        $publicField['hidden'] = isset($values['hidden']) ? $values['hidden'] : false;

        return $publicField;
    }
}