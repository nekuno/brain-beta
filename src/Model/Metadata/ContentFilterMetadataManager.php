<?php

namespace Model\Metadata;

use Model\Link\LinkModel;
use Model\Neo4j\GraphManager;
use Symfony\Component\Translation\Translator;

class ContentFilterMetadataManager extends MetadataManager
{
    protected function modifyPublicFieldByType($publicField, $name, $values)
    {
        $publicField = parent::modifyPublicFieldByType($publicField, $name, $values);

        $choiceOptions = $this->getChoiceOptions();

        if ($values['type'] === 'multiple_choices') {
            $publicField['choices'] = array();
            if (isset($choiceOptions[$name])) {
                $publicField['choices'] = $choiceOptions[$name];
            }
            if (isset($values['max_choices'])) {
                $publicField['max_choices'] = $values['max_choices'];
            }
        } elseif ($values['type'] === 'tags') {
            $publicField['top'] = $this->getTopContentTags($name);
        }

        return $publicField;
    }

    protected function getChoiceOptions()
    {
        return array(
            'type' => $this->getValidTypesLabels()
        );
    }

    protected function getValidTypesLabels()
    {
        $types = array();
        $keyTypes = LinkModel::getValidTypes();

        foreach ($keyTypes as $type) {
            $types[$type] = $this->translator->trans('types.' . lcfirst($type));
        };

        return $types;
    }

    protected function getTopContentTags($name)
    {
        return array();
    }

}