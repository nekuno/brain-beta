<?php

namespace Model\User;

use Everyman\Neo4j\Label;
use Everyman\Neo4j\Query\Row;
use Model\Metadata\ProfileMetadataManager;
use Model\Neo4j\GraphManager;

class ProfileOptionManager
{
    /**
     * @var GraphManager
     */
    protected $graphManager;

    /**
     * @var ProfileMetadataManager
     */
    protected $profileMetadataManager;

    public function __construct(GraphManager $graphManager, ProfileMetadataManager $profileMetadataManager)
    {
        $this->graphManager = $graphManager;
        $this->profileMetadataManager = $profileMetadataManager;
    }

    /**
     * Output choice options independently of locale
     * @return array
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getChoiceOptionIds()
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(option:ProfileOption)')
            ->returns("head(filter(x IN labels(option) WHERE x <> 'ProfileOption')) AS labelName, option.id AS id")
            ->orderBy('labelName');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $choiceOptions = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $typeName = $this->profileMetadataManager->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');

            $choiceOptions[$typeName][] = $optionId;
        }

        return $choiceOptions;
    }

    public function buildOptions(Row $row)
    {
        $options = $row->offsetGet('options');
        $optionsResult = array();
        /** @var Row $optionData */
        foreach ($options as $optionData) {

            list($optionId, $labels, $detail) = $this->getOptionData($optionData);
            /** @var Label[] $labels */
            foreach ($labels as $label) {

                $typeName = $this->profileMetadataManager->labelToType($label->getName());

                $metadata = $this->profileMetadataManager->getMetadata();
                switch ($metadata[$typeName]['type']) {
                    case 'multiple_choices':
                        $result = $this->getOptionArrayResult($optionsResult, $typeName, $optionId);
                        break;
                    case 'double_choice':
                    case 'tags_and_choice':
                        $result = $this->getOptionDetailResult($optionId, $detail);
                        break;
                    default:
                        $result = $optionId;
                        break;
                }

                $optionsResult[$typeName] = $result;
            }
        }

        return $optionsResult;
    }

    protected function getOptionData(Row $optionData = null)
    {
        if (!$optionData->offsetExists('option')) {
            return array(null, array(), null);
        }
        $optionNode = $optionData->offsetGet('option');
        $optionId = $optionNode->getProperty('id');

        /** @var Label[] $labels */
        $labels = $optionNode->getLabels();
        foreach ($labels as $key => $label) {
            if ($label->getName() === 'ProfileOption') {
                unset($labels[$key]);
            }
        }

        $detail = $optionData->offsetExists('detail') ? $optionData->offsetGet('detail') : '';

        return array($optionId, $labels, $detail);
    }

    protected function getOptionArrayResult($optionsResult, $typeName, $optionId)
    {
        if (isset($optionsResult[$typeName])) {
            $currentResult = is_array($optionsResult[$typeName]) ? $optionsResult[$typeName] : array($optionsResult[$typeName]);
            $currentResult[] = $optionId;
        } else {
            $currentResult = array($optionId);
        }

        return $currentResult;
    }

    protected function getOptionDetailResult($optionId, $detail)
    {
        return array('choice' => $optionId, 'detail' => $detail);
    }

}