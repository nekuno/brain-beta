<?php
/**
 * @author yawmoght <yawmoght@gmail.com>
 */

namespace Model\User;


use Model\LinkModel;
use Model\Neo4j\GraphManager;

class ContentFilterModel extends FilterModel
{
    /**
     * @var LinkModel
     */
    protected $linkModel;

    public function __construct(GraphManager $gm, LinkModel $linkModel, array $metadata, $defaultLocale)
    {
        parent::__construct($gm, $metadata, $defaultLocale);
        $this->linkModel = $linkModel;
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
        }

        return $publicField;
    }

    protected function getChoiceOptions($locale)
    {
        return array(
            'type' => $this->linkModel->getValidTypes($locale)
        );
    }

}