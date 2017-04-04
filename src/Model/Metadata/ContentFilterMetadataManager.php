<?php

namespace Model\Metadata;

use Model\Link\LinkModel;
use Model\Neo4j\GraphManager;
use Symfony\Component\Translation\Translator;

class ContentFilterMetadataManager extends FilterMetadataManager
{
    public function __construct(GraphManager $gm, Translator $translator, array $metadata, array $socialMetadata, $defaultLocale)
    {
        parent::__construct($gm, $translator, $metadata, $socialMetadata, $defaultLocale);
    }

    protected function modifyPublicFieldByType($publicField, $name, $values, $locale)
    {
        $publicField = parent::modifyPublicFieldByType($publicField, $name, $values, $locale);

        $choiceOptions = $this->getChoiceOptions($locale);

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

    protected function getChoiceOptions($locale)
    {
        return array(
            'type' => $this->getValidTypesLabels($locale)
        );
    }

    public function getValidTypesLabels($locale = 'en')
    {
        $this->translator->setLocale($locale);

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