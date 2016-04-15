<?php
/**
 * @author yawmoght <yawmoght@gmail.com>
 */

namespace Model\User;


use Everyman\Neo4j\Query\Row;

class ProfileFilterModel extends FilterModel
{

    protected function modifyPublicFieldByType($publicField, $name, $values, $locale)
    {
        $publicField = parent::modifyPublicFieldByType($publicField, $name, $values, $locale);

        $choiceOptions = $this->getChoiceOptions($locale);

        if ($values['type'] === 'choice') {
            $publicField['choices'] = array();
            if (isset($choiceOptions[$name])) {
                $publicField['choices'] = $choiceOptions[$name];
            }
        } elseif ($values['type'] === 'double_choice') {
            $publicField['choices'] = array();
            if (isset($choiceOptions[$name])) {
                $publicField['choices'] = $choiceOptions[$name];
                if (isset($values['doubleChoices'])) {
                    foreach ($values['doubleChoices'] as $choice => $doubleChoices) {
                        foreach ($doubleChoices as $doubleChoice => $doubleChoiceValues) {
                            $publicField['doubleChoices'][$choice][$doubleChoice] = $doubleChoiceValues[$locale];
                        }
                    }
                }
            }
        } elseif ($values['type'] === 'multiple_choices') {
            $publicField['choices'] = array();
            if (isset($choiceOptions[$name])) {
                $publicField['choices'] = $choiceOptions[$name];
            }
            if (isset($values['max_choices'])) {
                $publicField['max_choices'] = $values['max_choices'];
            }
        } elseif ($values['type'] === 'tags_and_choice') {
            $publicField['choices'] = array();
            if (isset($values['choices'])) {
                foreach ($values['choices'] as $choice => $description) {
                    $publicField['choices'][$choice] = $description[$locale];
                }
            }
            $publicField['top'] = $this->getTopProfileTags($name);
        } elseif ($values['type'] === 'tags') {
            $publicField['top'] = $this->getTopProfileTags($name);
        }

        return $publicField;
    }

    /**
     * Output  choice options according to user language
     * @param $locale
     * @return array
     * @throws \Model\Neo4j\Neo4jException
     */
    public function getChoiceOptions($locale)
    {
        $translationField = 'name_' . $locale;

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

        return $choiceOptions;
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

    public function getBirthdayRangeFromAgeRange($min = null, $max = null)
    {
        $return = array();
        if ($min){
            $now = new \DateTime();
            $maxBirthday = $now->modify('-'.$min.' years')->format('Y-m-d');
            $return ['max'] = $maxBirthday;
        }
        if ($max){
            $now = new \DateTime();
            $minBirthday = $now->modify('-'.$max.' years')->format('Y-m-d');
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

    protected function getTopProfileTags($tagType)
    {

        $tagLabelName = $this->typeToLabel($tagType);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(tag:' . $tagLabelName . ')-[tagged:TAGGED]-(profile:Profile)')
            ->returns('tag.name AS tag, count(*) as count')
            ->limit(5);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        $tags = array();
        foreach ($result as $row) {
            /* @var $row Row */
            $tags[] = $row->offsetGet('tag');
        }

        return $tags;
    }
}