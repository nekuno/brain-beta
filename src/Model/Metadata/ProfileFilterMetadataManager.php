<?php

namespace Model\Metadata;

use Everyman\Neo4j\Query\Row;

class ProfileFilterMetadataManager extends MetadataManager
{
    protected $profileOptions = array();
    protected $profileTags = array();

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
        $choiceOptions = $this->getChoiceOptions();

        switch ($values['type']) {
            case 'choice':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                break;
            case 'double_choice':
            case 'double_multiple_choices':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                $publicField = $this->addDoubleChoices($publicField, $values);
                break;
            case 'multiple_choices':
                $publicField = $this->addChoices($publicField, $name, $choiceOptions);
                $publicField['max_choices'] = isset($values['max_choices']) ? $values['max_choices'] : 999;
                break;
            case 'tags_and_choice':
                $publicField['choices'] = array();
                if (isset($values['choices'])) {
                    foreach ($values['choices'] as $choice => $description) {
                        $publicField['choices'][$choice] = $this->getLocaleString($description);
                    }
                }
                $publicField['top'] = $this->getTopProfileTags($name);
                break;
            case 'tags':
                $publicField['top'] = $this->getTopProfileTags($name);
                break;
            default:
                break;
        }

        return $publicField;
    }

    /**
     * Output  choice options according to user language
     * @return array
     * @throws \Model\Neo4j\Neo4jException
     */
    protected function getChoiceOptions()
    {
        $translationField = $this->getTranslationField();
        if (isset($this->profileOptions[$translationField])) {
            return $this->profileOptions[$translationField];
        }
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(option:ProfileOption)')
            ->returns("head(filter(x IN labels(option) WHERE x <> 'ProfileOption')) AS labelName, option.id AS id, option." . $translationField . " AS name")
            ->orderBy('labelName');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $choiceOptions = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $typeName = $this->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');
            $optionName = $row->offsetGet('name');

            $choiceOptions[$typeName][$optionId] = $optionName;
        }

        $this->profileOptions[$translationField] = $choiceOptions;

        return $choiceOptions;
    }

    protected function getTranslationField()
    {
        $locale = $this->translator->getLocale();

        return 'name_' . $locale;
    }

    /**
     * Output choice options independently of locale
     * @return array
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getChoiceOptionIds()
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(option:ProfileOption)')
            ->returns("head(filter(x IN labels(option) WHERE x <> 'ProfileOption')) AS labelName, option.id AS id")
            ->orderBy('labelName');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $choiceOptions = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $typeName = $this->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');

            $choiceOptions[$typeName][] = $optionId;
        }

        return $choiceOptions;
    }

    public function splitFilters($filters)
    {
        $filters['profileFilters'] = (isset($filters['profileFilters']) && is_array($filters['profileFilters'])) ? $filters['profileFilters'] : array();
        $profileMetadata = $this->getMetadata();
        foreach ($profileMetadata as $fieldName => $fieldData) {
            if (isset($filters['userFilters'][$fieldName])) {
                $filters['profileFilters'][$fieldName] = $filters['userFilters'][$fieldName];
                unset($filters['userFilters'][$fieldName]);
            }
        }

        return $filters;
    }

    public function labelToType($labelName)
    {

        return lcfirst($labelName);
    }

    public function typeToLabel($typeName)
    {
        return ucfirst($typeName);
    }

    public function getAgeRangeFromBirthdayRange(array $birthday)
    {
        $min = $this->getYearsFromDate($birthday['min']);
        $max = $this->getYearsFromDate($birthday['max']);

        return array('min' => $max, 'max' => $min);
    }

    public function getYearsFromDate($birthday)
    {
        $minDate = new \DateTime($birthday);
        $minInterval = $minDate->diff(new \DateTime());

        return $minInterval->y;
    }

    public function getBirthdayRangeFromAgeRange($min = null, $max = null, $nowDate = null)
    {
        $return = array('max' => null, 'min' => null);
        if ($min) {
            $now = new \DateTime($nowDate);
            $maxBirthday = $now->modify('-' . ($min) . ' years')->format('Y-m-d');
            $return ['max'] = $maxBirthday;
        }
        if ($max) {
            $now = new \DateTime($nowDate);
            $minBirthday = $now->modify('-' . ($max + 1) . ' years')->modify('+ 1 days')->format('Y-m-d');
            $return['min'] = $minBirthday;
        }

        return $return;
    }

    public function getLanguageFromTag($tag)
    {
        return $this->translateTypicalLanguage($this->formatLanguage($tag));
    }

    public function formatLanguage($typeName)
    {
        $firstCharacter = mb_strtoupper(mb_substr($typeName, 0, 1, 'UTF-8'), 'UTF-8');
        $restString = mb_strtolower(mb_substr($typeName, 1, null, 'UTF-8'), 'UTF-8');

        return $firstCharacter . $restString;
    }

    //TODO: Refactor this translation functions
    public function translateTypicalLanguage($language)
    {
        switch ($language) {
            case 'Español':
                return 'Spanish';
            case 'Castellano':
                return 'Spanish';
            case 'Inglés':
                return 'English';
            case 'Ingles':
                return 'English';
            case 'Francés':
                return 'French';
            case 'Frances':
                return 'French';
            case 'Alemán':
                return 'German';
            case 'Aleman':
                return 'German';
            case 'Portugués':
                return 'Portuguese';
            case 'Portugues':
                return 'Portuguese';
            case 'Italiano':
                return 'Italian';
            case 'Chino':
                return 'Chinese';
            case 'Japonés':
                return 'Japanese';
            case 'Japones':
                return 'Japanese';
            case 'Ruso':
                return 'Russian';
            case 'Árabe':
                return 'Arabic';
            case 'Arabe':
                return 'Arabic';
            default:
                return $language;
        }
    }

    public function translateLanguageToLocale($language, $locale)
    {
        $locale = $this->getLocale($locale);

        if ($locale === 'en') {
            return $language;
        }
        if ($locale === 'es') {
            switch ($language) {
                case 'Spanish':
                    return 'Español';
                case 'English':
                    return 'Inglés';
                case 'French':
                    return 'Francés';
                case 'German':
                    return 'Alemán';
                case 'Portuguese':
                    return 'Portugués';
                case 'Italian':
                    return 'Italiano';
                case 'Chinese':
                    return 'Chino';
                case 'Japanese':
                    return 'Japonés';
                case 'Russian':
                    return 'Ruso';
                case 'Arabic':
                    return 'Árabe';
            }
        }

        return $language;
    }

    protected function getTopProfileTags($tagType)
    {
        $tagLabelName = $this->typeToLabel($tagType);
        if (isset($this->profileTags[$tagLabelName])) {
            return $this->profileTags[$tagLabelName];
        }
        $qb = $this->gm->createQueryBuilder();
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

        $this->profileTags[$tagLabelName] = $tags;

        return $tags;
    }

    /**
     * @param $publicField
     * @param $name
     * @param $choiceOptions
     * @return mixed
     */
    protected function addChoices($publicField, $name, $choiceOptions)
    {
        $publicField['choices'] = isset($choiceOptions[$name]) ? $choiceOptions[$name] : array();

        return $publicField;
    }

    /**
     * @param $publicField
     * @param $values
     * @return mixed
     */
    protected function addDoubleChoices($publicField, $values)
    {
        $valueDoubleChoices = isset($values['doubleChoices']) ? $values['doubleChoices'] : array();
        foreach ($valueDoubleChoices as $choice => $doubleChoices) {
            foreach ($doubleChoices as $doubleChoice => $doubleChoiceValues) {
                $publicField['doubleChoices'][$choice][$doubleChoice] = $this->getLocaleString($doubleChoiceValues);
            }
        }

        return $publicField;
    }
}