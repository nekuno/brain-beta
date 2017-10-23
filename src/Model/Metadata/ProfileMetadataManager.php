<?php

namespace Model\Metadata;

class ProfileMetadataManager extends MetadataManager
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
        $locale = $this->translator->getLocale();

        $choiceOptions = $this->profileOptionManager->getLocaleOptions($locale);

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
                $publicField['top'] = $this->profileOptionManager->getTopProfileTags($name);
                break;
            case 'tags':
                $publicField['top'] = $this->profileOptionManager->getTopProfileTags($name);
                break;
            default:
                break;
        }

        return $publicField;
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


}