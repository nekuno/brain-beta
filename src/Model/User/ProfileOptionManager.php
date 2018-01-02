<?php

namespace Model\User;

use Everyman\Neo4j\Label;
use Everyman\Neo4j\Query\Row;
use Model\Metadata\MetadataUtilities;
use Model\Metadata\ProfileMetadataManager;
use Model\Neo4j\GraphManager;

class ProfileOptionManager
{
    protected $graphManager;
    protected $profileMetadataManager;
    protected $metadataUtilities;

    protected $options = array();
    protected $tags = array();

    public function __construct(GraphManager $graphManager, ProfileMetadataManager $profileMetadataManager, MetadataUtilities $metadataUtilities)
    {
        $this->graphManager = $graphManager;
        $this->profileMetadataManager = $profileMetadataManager;
        $this->metadataUtilities = $metadataUtilities;
    }

    /**
     * Output choice options independently of locale
     * @return array
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getOptions()
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
            $typeName = $this->metadataUtilities->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');

            $choiceOptions[$typeName][] = $optionId;
        }

        return $choiceOptions;
    }

    public function getUserProfileOptions($id)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(option:ProfileOption)-[optionOf:OPTION_OF]->(profile:Profile)-[:PROFILE_OF]->(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', $id)
            ->returns('profile, collect(distinct {option: option, detail: (CASE WHEN EXISTS(optionOf.detail) THEN optionOf.detail ELSE null END)}) AS options');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        $options = array();
        foreach ($result as $row) {
            $options += $this->buildOptions($row->offsetGet('options'));
        }

        return $options;
    }

    /**
     * Output  choice options according to user language
     * @param $locale
     * @return array
     */
    public function getLocaleOptions($locale)
    {
        $translationField = $this->getTranslationField($locale);
        if (isset($this->options[$translationField])) {
            return $this->options[$translationField];
        }
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(option:ProfileOption)')
            ->returns("head(filter(x IN labels(option) WHERE x <> 'ProfileOption')) AS labelName, option.id AS id, option." . $translationField . " AS name")
            ->orderBy('labelName');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $choiceOptions = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $typeName = $this->metadataUtilities->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');
            $optionName = $row->offsetGet('name');

            $choiceOptions[$typeName][$optionId] = $optionName;
        }

        $this->options[$translationField] = $choiceOptions;

        return $choiceOptions;
    }

    protected function getTranslationField($locale)
    {
        return 'name_' . $locale;
    }

    public function buildOptions(\ArrayAccess $options)
    {
        $optionsResult = array();
        $metadata = $this->profileMetadataManager->getMetadata();

        /** @var Row $optionData */
        foreach ($options as $optionData) {

            list($optionId, $labels, $detail) = $this->getOptionData($optionData);
            /** @var Label[] $labels */
            foreach ($labels as $label) {

                $typeName = $this->metadataUtilities->labelToType($label->getName());

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

    public function getTopProfileTags($tagType)
    {
        $tagLabelName = $this->metadataUtilities->typeToLabel($tagType);
        if (isset($this->tags[$tagLabelName])) {
            return $this->tags[$tagLabelName];
        }
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(tag:' . $tagLabelName . ')-[tagged:TAGGED]->(profile:Profile)')
            ->returns('tag.name AS tag, count(*) as count')
            ->limit(5);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $tags = array();
        foreach ($result as $row) {
            /* @var $row Row */
            $tags[] = $row->offsetGet('tag');
        }

        $this->tags[$tagLabelName] = $tags;

        return $tags;
    }

    public function getUserProfileTags($id)
    {
        $qb = $this->graphManager->createQueryBuilder();
        $qb->match('(tag:ProfileTag)-[tagged:TAGGED]->(profile:Profile)-[:PROFILE_OF]->(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', $id)
            ->returns('profile', 'collect(distinct {tag: tag, tagged: tagged}) AS tags');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $tags = array();
        foreach ($result as $row) {
            $tags += $this->buildTags($row);
        }

        return $tags;
    }

    public function buildTags(Row $row, $locale = null)
    {
        $tags = $row->offsetGet('tags');
        $tagsResult = array();
        /** @var Row $tagData */
        foreach ($tags as $tagData) {
            $tag = $tagData->offsetGet('tag');
            $tagged = $tagData->offsetGet('tagged');
            $labels = $tag ? $tag->getLabels() : array();

            /* @var Label $label */
            foreach ($labels as $label) {
                if ($label->getName() && $label->getName() != 'ProfileTag') {
                    $typeName = $this->metadataUtilities->labelToType($label->getName());
                    $tagResult = $tag->getProperty('name');
                    $detail = $tagged->getProperty('detail');
                    if (!is_null($detail)) {
                        $tagResult = array();
                        $tagResult['tag'] = $tag->getProperty('name');
                        $tagResult['choice'] = $detail;
                    }
                    if ($typeName === 'language') {
                        if (is_null($detail)) {
                            $tagResult = array();
                            $tagResult['tag'] = $tag->getProperty('name');
                            $tagResult['choice'] = '';
                        }
                        $tagResult['tag'] = $this->metadataUtilities->translateLanguageToLocale($tagResult['tag'], $locale);
                    }
                    $tagsResult[$typeName][] = $tagResult;
                }
            }
        }

        return $tagsResult;
    }

}