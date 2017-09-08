<?php

namespace Service\Validator;

use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;

class Validator implements ValidatorInterface
{
    const MAX_TAGS_AND_CHOICE_LENGTH = 15;
    const LATITUDE_REGEX = '/^-?([1-8]?[0-9]|[1-9]0)\.{1}\d+$/';
    const LONGITUDE_REGEX = '/^-?([1]?[0-7][1-9]|[1]?[1-8][0]|[1-9]?[0-9])\.{1}\d+$/';

    protected $existenceValidator;

    /**
     * @var array Section from yml config file, chosen by Factory
     */
    protected $metadata;

    public function __construct(GraphManager $graphManager, array $metadata)
    {
        $this->metadata = $metadata;
        $this->existenceValidator = new ExistenceValidator($graphManager);
    }

    public function validateOnCreate($data)
    {
        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);
    }

    public function validateOnUpdate($data)
    {
        $metadata = $this->metadata;
        $this->validateMetadata($data, $metadata);
    }

    public function validateOnDelete($data)
    {

    }

    public function validateUserId($userId, $desired = true)
    {
        if (!is_int($userId)) {
            $errors = array('userId' => array('User Id must be an integer'));
        } else {
            $errors = array('userId' => $this->existenceValidator->validateUserId($userId, $desired));
        }

        $this->throwException($errors);
    }

    protected function validateUserInData(array $data, $userIdRequired = true)
    {
        $isMissing = $userIdRequired && (!isset($data['userId']) || null === $data['userId']);
        if ($isMissing) {
            $this->throwException(array('userId', 'User id is required for this action'));
        }

        if (isset($data['userId'])) {
            $this->validateUserId($data['userId']);
        }
    }

    protected function validateExtraFields($data, $metadata)
    {
        $errors = array();

        $diff = array_diff_key($data, $metadata);
        if (count($diff) > 0) {
            foreach ($diff as $invalidKey => $invalidValue) {
                $errors[$invalidKey] = array(sprintf('Invalid key "%s"', $invalidKey));
            }
        }

        $this->throwException($errors);
    }

    protected function validateMetadata($data, $metadata, $dataChoices = array())
    {
        $errors = array();
        foreach ($metadata as $fieldName => $fieldData) {

            $fieldErrors = array();
            $choices = $this->buildChoices($dataChoices, $fieldData, $fieldName);

            if (isset($data[$fieldName])) {

                $dataValue = $data[$fieldName];
                switch ($fieldData['type']) {
                    case 'text':
                    case 'textarea':
                        if (isset($fieldData['min'])) {
                            if (strlen($dataValue) < $fieldData['min']) {
                                $fieldErrors[] = 'Must have ' . $fieldData['min'] . ' characters min.';
                            }
                        }
                        if (isset($fieldData['max'])) {
                            if (strlen($dataValue) > $fieldData['max']) {
                                $fieldErrors[] = 'Must have ' . $fieldData['max'] . ' characters max.';
                            }
                        }
                        break;

                    case 'integer':
                        if (!is_integer($dataValue)) {
                            $fieldErrors[] = 'Must be an integer';
                        }
                        if (isset($fieldData['min'])) {
                            if (!empty($dataValue) && $dataValue < $fieldData['min']) {
                                $fieldErrors[] = 'Must be greater than ' . $fieldData['min'];
                            }
                        }
                        if (isset($fieldData['max'])) {
                            if ($dataValue > $fieldData['max']) {
                                $fieldErrors[] = 'Must be less than ' . $fieldData['max'];
                            }
                        }
                        break;

                    case 'array':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Must be an array';
                        } else {
                            if (isset($fieldData['min'])) {
                                if (count($dataValue) < $fieldData['min']) {
                                    $fieldErrors[] = 'Array length must be greater than ' . $fieldData['min'];
                                }
                            }
                            if (isset($fieldData['max'])) {
                                if (count($dataValue) > $fieldData['max']) {
                                    $fieldErrors[] = 'Array length must be less than ' . $fieldData['max'];
                                }
                            }
                        }
                        break;
                    case 'birthday_range':
                    case 'integer_range':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Must be an array';
                            continue;
                        }
                        if (isset($dataValue['max']) && !is_int($dataValue['max'])) {
                            $fieldErrors[] = 'Maximum value must be an integer';
                        }
                        if (isset($dataValue['min']) && !is_int($dataValue['min'])) {
                            $fieldErrors[] = 'Minimum value must be an integer';
                        }
                        if (isset($fieldData['min']) && isset($dataValue['min']) && $dataValue['min'] < $fieldData['min']) {
                            $fieldErrors[] = 'Minimum value must be greater than ' . $fieldData['min'];
                        }
                        if (isset($fieldData['max']) && isset($dataValue['max']) && $dataValue['max'] > $fieldData['max']) {
                            $fieldErrors[] = 'Maximum value must be less than ' . $fieldData['max'];
                        }
                        if (isset($dataValue['min']) && isset($dataValue['max']) && $dataValue['min'] > $dataValue['max']) {
                            $fieldErrors[] = 'Minimum value must be smaller or equal than maximum value';
                        }
                        break;

                    case 'date':
                        $date = \DateTime::createFromFormat('Y-m-d', $dataValue);
                        if (!($date && $date->format('Y-m-d') == $dataValue)) {
                            $fieldErrors[] = 'Invalid date format, valid format is "Y-m-d".';
                        }
                        break;

                    case 'birthday':
                        if (!is_string($dataValue)) {
                            $fieldErrors[] = 'Birthday value must be a string';
                            continue;
                        }
                        $date = \DateTime::createFromFormat('Y-m-d', $dataValue);
                        $now = new \DateTime();
                        if (!($date && $date->format('Y-m-d') == $dataValue)) {
                            $fieldErrors[] = 'Invalid date format, valid format is "YYYY-MM-DD".';
                        } elseif ($now < $date) {
                            $fieldErrors[] = 'Invalid birthday date, can not be in the future.';
                        } elseif ($now->modify('-14 year') < $date) {
                            $fieldErrors[] = 'Invalid birthday date, you must be older than 14 years.';
                        }
                        break;

                    case 'boolean':
                        $fieldErrors = $this->validateBoolean($dataValue);
                        break;

                    case 'choice':
                        if (!in_array($dataValue, $choices)) {
                            $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $dataValue, implode("', '", $choices));
                        }
                        break;

                    case 'double_choice':
                        $thisChoices = $choices + array('' => '');
                        if (!in_array($dataValue['choice'], $thisChoices)) {
                            $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $dataValue['choice'], implode("', '", $thisChoices));
                        }
                        $doubleChoices = $fieldData['doubleChoices'] + array('' => '');
                        if (!isset($doubleChoices[$dataValue['choice']]) || isset($dataValue['detail']) && $dataValue['detail'] && !isset($doubleChoices[$dataValue['choice']][$dataValue['detail']])) {
                            $fieldErrors[] = sprintf('Option choice and detail must be set in "%s"', $dataValue['choice']);
                        } elseif ($dataValue['detail'] && !in_array($dataValue['detail'], array_keys($doubleChoices[$dataValue['choice']]))) {
                            $fieldErrors[] = sprintf('Detail with value "%s" is not valid, possible values are "%s"', $dataValue['detail'], implode("', '", array_keys($doubleChoices)));
                        }
                        break;
                    case 'double_multiple_choices':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Multiple choices value must be an array';
                            continue;
                        }
                        $thisChoices = $choices + array('' => '');
                        $doubleChoices = $fieldData['doubleChoices'] + array('' => '');
                        foreach ($dataValue as $singleDataValue) {
                            if (!in_array($singleDataValue['choice'], $thisChoices)) {
                                $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $singleDataValue['choice'], implode("', '", $thisChoices));
                            }
                            if (!isset($doubleChoices[$singleDataValue['choice']]) || isset($singleDataValue['detail']) && $singleDataValue['detail'] && !isset($doubleChoices[$singleDataValue['choice']][$singleDataValue['detail']])) {
                                $fieldErrors[] = sprintf('Option choice and detail must be set in "%s"', $singleDataValue['choice']);
                            } elseif (isset($singleDataValue['detail']) && $singleDataValue['detail'] && !in_array($singleDataValue['detail'], array_keys($doubleChoices[$singleDataValue['choice']]))) {
                                $fieldErrors[] = sprintf('Detail with value "%s" is not valid, possible values are "%s"', $singleDataValue['detail'], implode("', '", array_keys($doubleChoices)));
                            }
                        }
                        break;
                    case 'tags':
                        break;
                    case 'tags_and_choice':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Tags and choice value must be an array';
                        }
                        if (count($dataValue) > self::MAX_TAGS_AND_CHOICE_LENGTH) {
                            $fieldErrors[] = sprintf('Tags and choice length "%s" is too long. "%s" is the maximum', count($dataValue), self::MAX_TAGS_AND_CHOICE_LENGTH);
                        }
                        foreach ($dataValue as $tagAndChoice) {
                            if (!isset($tagAndChoice['tag']) || !array_key_exists('choice', $tagAndChoice)) {
                                $fieldErrors[] = sprintf('Tag and choice must be defined for tags and choice type');
                            }
                            if (isset($tagAndChoice['choice']) && $tagAndChoice['choice'] && isset($choices) && !in_array($tagAndChoice['choice'], array_keys($choices))) {
                                $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $tagAndChoice['choice'], implode("', '", array_keys($choices)));
                            }
                        }
                        break;
                    case 'tags_and_multiple_choices':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Tags and multiple choices value must be an array';
                        }
                        if (count($dataValue) > self::MAX_TAGS_AND_CHOICE_LENGTH) {
                            $fieldErrors[] = sprintf('Tags and multiple choices length "%s" is too long. "%s" is the maximum', count($dataValue), self::MAX_TAGS_AND_CHOICE_LENGTH);
                        }
                        foreach ($dataValue as $tagAndMultipleChoices) {
                            if (!isset($tagAndMultipleChoices['tag']) || !array_key_exists('choices', $tagAndMultipleChoices)) {
                                $fieldErrors[] = sprintf('Tag and choices must be defined for tags and multiple choices type');
                            }
                            if (isset($tagAndMultipleChoices['choices'])) {
                                foreach ($tagAndMultipleChoices['choices'] as $singleChoice) {
                                    if ($singleChoice && isset($choices) && !in_array($singleChoice, array_keys($choices))) {
                                        $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $singleChoice, implode("', '", array_keys($choices)));
                                    }
                                }
                            }
                        }
                        break;
                    case 'multiple_choices':
                        $multipleChoices = $choices;
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'Multiple choices value must be an array';
                            continue;
                        }

                        if (isset($fieldData['max_choices'])) {
                            if (count($dataValue) > $fieldData['max_choices']) {
                                $fieldErrors[] = sprintf('Option length "%s" is too long. "%s" is the maximum', count($dataValue), $fieldData['max_choices']);
                            }
                        }
                        foreach ($dataValue as $singleValue) {
                            if (!in_array($singleValue, $multipleChoices)) {
                                $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $singleValue, implode("', '", $multipleChoices));
                            }
                        }
                        break;
                    case 'location':
                        foreach ($this->validateLocation($dataValue) as $error) {
                            $fieldErrors[] = $error;
                        }

                        break;
                    case 'location_distance':
                        if (!is_array($dataValue)) {
                            $fieldErrors[] = 'The location distance value must be an array';
                            continue;
                        }
                        if (!isset($dataValue['distance'])) {
                            $fieldErrors[] = 'Distance required';
                        }
                        if (!isset($dataValue['location'])) {
                            $fieldErrors[] = 'Location required';
                            continue;
                        }

                        foreach ($this->validateLocation($dataValue['location']) as $error) {
                            $fieldErrors[] = $error;
                        }
                        break;
                    case 'email':
                        if (!filter_var($dataValue, FILTER_VALIDATE_EMAIL)) {
                            $fieldErrors[] = 'Value must be a valid email';
                        }
                        break;
                    case 'url':
                        if (!filter_var($dataValue, FILTER_VALIDATE_URL)) {
                            $fieldErrors[] = 'Value must be a valid URL';
                        }
                        break;
                    case 'image_path':
                        if (!preg_match('/^[\w\/\\-]+\.(png|jpe?g|gif|tiff)$/i', $dataValue)) {
                            $fieldErrors[] = 'Value must be a valid path';
                        }
                        break;
                    case 'timestamp':
                        if (!(is_int($dataValue) || is_double($dataValue))) {
                            $fieldErrors[] = 'Value must be a valid timestamp';
                        }
                        break;
                    case 'string':
                        if (!is_string($dataValue)) {
                            $fieldErrors[] = 'Value must be a string';
                        }
                        break;
                    case 'order':
                        if (!in_array($dataValue, array('similarity', 'matching'))) {
                            $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $dataValue, implode("', '", array('similarity', 'matching')));
                        }
                        break;
                }
            } else {
                if (isset($fieldData['required']) && $fieldData['required'] === true) {
                    $fieldErrors[] = 'It\'s required.';
                }
            }

            if (count($fieldErrors) > 0) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        $this->throwException($errors);

        return true;
    }

    /**
     * @param $errors
     */
    protected function throwException($errors)
    {
        foreach ($errors as $field => $fieldErrors){
            if (empty($fieldErrors)){
                unset($errors[$field]);
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @param $dataChoices
     * @param $fieldData
     * @param $fieldName
     * @return array
     */
    protected function buildChoices($dataChoices, $fieldData, $fieldName)
    {
        $fieldChoices = isset($fieldData['choices']) ? $fieldData['choices'] : array();
        $thisDataChoices = isset($dataChoices[$fieldName]) ? $dataChoices[$fieldName] : array();

        return array_merge($fieldChoices, $thisDataChoices);
    }

    private function validateLocation($dataValue)
    {
        $fieldErrors = array();
        if (!is_array($dataValue)) {
            $fieldErrors[] = sprintf('The value "%s" is not valid, it should be an array with "latitude" and "longitude" keys', $dataValue);
        } else {
            if (!isset($dataValue['address']) || !$dataValue['address'] || !is_string($dataValue['address'])) {
                $fieldErrors[] = 'Address required';
            } else {
                if (!isset($dataValue['latitude']) || !preg_match(Validator::LATITUDE_REGEX, $dataValue['latitude'])) {
                    $fieldErrors[] = 'Latitude not valid';
                } elseif (!is_float($dataValue['latitude'])) {
                    $fieldErrors[] = 'Latitude must be float';
                }
                if (!isset($dataValue['longitude']) || !preg_match(Validator::LONGITUDE_REGEX, $dataValue['longitude'])) {
                    $fieldErrors[] = 'Longitude not valid';
                } elseif (!is_float($dataValue['longitude'])) {
                    $fieldErrors[] = 'Longitude must be float';
                }
                if (!isset($dataValue['locality']) || !$dataValue['locality'] || !is_string($dataValue['locality'])) {
                    $fieldErrors[] = 'Locality required';
                }
                if (!isset($dataValue['country']) || !$dataValue['country'] || !is_string($dataValue['country'])) {
                    $fieldErrors[] = 'Country required';
                }
            }
        }

        return $fieldErrors;
    }

    protected function validateBoolean($value, $name = null)
    {
        $errors = array();
        if (!is_bool($value)) {
            $fieldErrors[] = sprintf('%s must be a boolean, %s given', $name, $value);
        }

        return $errors;
    }

}