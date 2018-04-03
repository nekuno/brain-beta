<?php

namespace Service\Validator;

use Model\Exception\ErrorList;
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
        $errors = new ErrorList();
        if (!is_int($userId)) {
            $errors->addError('userId', 'User Id must be an integer');
        } else {
            $errors->setErrors('userId', $this->existenceValidator->validateUserId($userId, $desired));
        }

        $this->throwException($errors);
    }

    protected function validateUserInData(array $data, $userIdRequired = true)
    {
        $isMissing = $userIdRequired && (!isset($data['userId']) || null === $data['userId']);
        if ($isMissing) {
            $errors = new ErrorList();
            $errors->addError('userId', 'User id is required for this action');
            $this->throwException($errors);
        }

        if (isset($data['userId'])) {
            $this->validateUserId($data['userId']);
        }
    }

    protected function validateExtraFields($data, $metadata)
    {
        $errors = new ErrorList();

        $diff = array_diff_key($data, $metadata);
        foreach ($diff as $invalidKey => $invalidValue) {
            $errors->addError($invalidKey, sprintf('Invalid key "%s"', $invalidKey));
        }

        $this->throwException($errors);
    }

    protected function validateMetadata($data, $metadata, $dataChoices = array())
    {
        $errors = new ErrorList();
        foreach ($metadata as $fieldName => $fieldData) {

            $choices = $this->buildChoices($dataChoices, $fieldData, $fieldName);

            $isDataSet = isset($data[$fieldName]) && !(is_array($data[$fieldName]) && empty($data[$fieldName]));
            if ($isDataSet) {

                $dataValue = $data[$fieldName];
                switch ($fieldData['type']) {
                    case 'text':
                    case 'textarea':
                        if (isset($fieldData['min'])) {
                            if (strlen($dataValue) < $fieldData['min']) {
                                $errors->addError($fieldName, 'Must have ' . $fieldData['min'] . ' characters min.');
                            }
                        }
                        if (isset($fieldData['max'])) {
                            if (strlen($dataValue) > $fieldData['max']) {
                                $errors->addError($fieldName, 'Must have ' . $fieldData['max'] . ' characters max.');
                            }
                        }
                        break;

                    case 'integer':
                        if (!is_integer($dataValue)) {
                            $errors->addError($fieldName, 'Must be an integer');
                        }
                        if (isset($fieldData['min'])) {
                            if (!empty($dataValue) && $dataValue < $fieldData['min']) {
                                $errors->addError($fieldName, 'Must be greater than ' . $fieldData['min']);
                            }
                        }
                        if (isset($fieldData['max'])) {
                            if ($dataValue > $fieldData['max']) {
                                $errors->addError($fieldName, 'Must be less than ' . $fieldData['max']);
                            }
                        }
                        break;

                    case 'array':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Must be an array');
                        } else {
                            $errors->addError($fieldName, $this->validateMin($dataValue, $fieldData));
                            $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData));
                        }
                        break;
                    case 'birthday_range':
                    case 'integer_range':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Must be an array');
                            continue;
                        }
                        if (isset($dataValue['max']) && !is_int($dataValue['max'])) {
                            $errors->addError($fieldName, 'Maximum value must be an integer');
                        }
                        if (isset($dataValue['min']) && !is_int($dataValue['min'])) {
                            $errors->addError($fieldName, 'Minimum value must be an integer');
                        }
                        if (isset($fieldData['min']) && isset($dataValue['min']) && $dataValue['min'] < $fieldData['min']) {
                            $errors->addError($fieldName, 'Minimum value must be greater than ' . $fieldData['min']);
                        }
                        if (isset($fieldData['max']) && isset($dataValue['max']) && $dataValue['max'] > $fieldData['max']) {
                            $errors->addError($fieldName, 'Maximum value must be less than ' . $fieldData['max']);
                        }
                        if (isset($dataValue['min']) && isset($dataValue['max']) && $dataValue['min'] > $dataValue['max']) {
                            $errors->addError($fieldName, 'Minimum value must be smaller or equal than maximum value');
                        }
                        break;

                    case 'date':
                        $errors->addError($fieldName, $this->validateDateFormat($dataValue));
                        break;

                    case 'birthday':
                        $errors->addError($fieldName, $this->validateString($dataValue));
                        if ($errors->hasFieldErrors($fieldName)){
                            continue;
                        }

                        $errors->addError($fieldName, $this->validateDateFormat($dataValue));

                        $date = \DateTime::createFromFormat('Y-m-d', $dataValue);
                        $now = new \DateTime();

                        if ($now < $date) {
                            $errors->addError($fieldName, 'Invalid birthday date, can not be in the future.');
                        } elseif ($now->modify('-14 year') < $date) {
                            $errors->addError($fieldName, 'Invalid birthday date, you must be older than 14 years.');
                        }
                        break;

                    case 'boolean':
                        $errors->addError($fieldName, $this->validateBoolean($dataValue));
                        break;

                    case 'choice':
                        $errors->addError($fieldName, $this->validateChoice($dataValue, $choices));
                        break;

                    case 'double_choice':
                        $thisChoices = $choices + array('' => '');
                        $errors->addError($fieldName, $this->validateChoice($dataValue['choice'], $thisChoices));

                        $doubleChoices = $fieldData['doubleChoices'] + array('' => '');
                        if (!isset($doubleChoices[$dataValue['choice']]) || isset($dataValue['detail']) && $dataValue['detail'] && !isset($doubleChoices[$dataValue['choice']][$dataValue['detail']])) {
                            $errors->addError($fieldName, sprintf('Option choice and detail must be set in "%s"', $dataValue['choice']));
                        } elseif ($dataValue['detail']) {
                            $errors->addError($fieldName, $this->validateChoice($dataValue['detail'], array_keys($doubleChoices[$dataValue['choice']])));
                        }
                        break;
                    case 'double_multiple_choices':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Double multiple choices value must be an array');
                            continue;
                        }
                        $thisChoices = $choices + array('' => '');
                        $doubleChoices = $fieldData['doubleChoices'] + array('' => '');

                        if (!isset($dataValue['choices']) || !is_array($dataValue['choices'])) {
                            $errors->addError($fieldName, sprintf('Option choices must be set and must be an array in "%s"', $fieldName));
                        }
                        if (isset($dataValue['details']) && !is_array($dataValue['details'])) {
                            $errors->addError($fieldName, sprintf('Details must be an array in "%s"', $fieldName));
                        }

                        foreach ($dataValue['choices'] as $choice) {
                            $errors->addError($fieldName, $this->validateChoice($choice, $thisChoices));
                            $details = isset($dataValue['details']) ? $dataValue['details'] : array();
                            foreach ($details as $detail) {
                                if (!isset($doubleChoices[$choice][$detail])) {
                                    $errors->addError($fieldName, sprintf('Detail with value "%s" is not valid, possible values are "%s"', $detail, implode("', '", array_keys($doubleChoices))));
                                }
                            }
                        }
                        break;
                    case 'choice_and_multiple_choices':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Choice and multiple choices value must be an array');
                            continue;
                        }
                        $thisChoices = $choices + array('' => '');
                        $doubleChoices = $fieldData['doubleChoices'] + array('' => '');

                        if (!isset($dataValue['choice'])) {
                            $errors->addError($fieldName, sprintf('Option choice must be set in "%s"', $fieldName));
                        }
                        if (isset($dataValue['details']) && !is_array($dataValue['details'])) {
                            $errors->addError($fieldName, sprintf('Details must be an array in "%s"', $fieldName));
                        }
                        $choice = $dataValue['choice'];
                        $this->validateChoice($choice, $thisChoices);
                        $details = isset($dataValue['details']) ? $dataValue['details'] : array();
                        foreach ($details as $detail) {
                            if (!isset($doubleChoices[$choice][$detail])) {
                                $errors->addError($fieldName, sprintf('Detail with value "%s" is not valid, possible values are "%s"', $detail, implode("', '", array_keys($doubleChoices))));
                            }
                        }
                        break;
                    case 'tags':
                        $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData, self::MAX_TAGS_AND_CHOICE_LENGTH));
                        break;
                    case 'tags_and_choice':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Tags and choice value must be an array');
                        }
                        $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData, self::MAX_TAGS_AND_CHOICE_LENGTH));

                        foreach ($dataValue as $tagAndChoice) {
                            if (!isset($tagAndChoice['tag']) || !array_key_exists('choice', $tagAndChoice)) {
                                $errors->addError($fieldName, sprintf('Tag and choice must be defined for tags and choice type'));
                            }
                            if (isset($tagAndChoice['choice']) && $tagAndChoice['choice']) {
                                $errors->addError($fieldName, $this->validateChoice($tagAndChoice['choice'], array_keys($choices)));
                            }
                        }
                        break;
                    case 'tags_and_multiple_choices':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Tags and multiple choices value must be an array');
                        }
                        $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData, self::MAX_TAGS_AND_CHOICE_LENGTH));

                        foreach ($dataValue as $tagAndMultipleChoices) {
                            if (!isset($tagAndMultipleChoices['tag']) || !array_key_exists('choices', $tagAndMultipleChoices)) {
                                $errors->addError($fieldName, sprintf('Tag and choices must be defined for tags and multiple choices type'));
                            }
                            if (isset($tagAndMultipleChoices['choices'])) {
                                foreach ($tagAndMultipleChoices['choices'] as $singleChoice) {
                                    $errors->addError($fieldName, $this->validateChoice($singleChoice, array_keys($choices)));
                                }
                            }
                        }
                        break;
                    case 'multiple_choices':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Multiple choices value must be an array');
                            continue;
                        }

                        $errors->addError($fieldName, $this->validateMin($dataValue, $fieldData));
                        $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData));

                        foreach ($dataValue as $singleValue) {
                            $errors->addError($fieldName, $this->validateChoice($singleValue, $choices));
                        }
                        break;
                    case 'location':
                        $errors->setErrors($fieldName, $this->validateLocation($dataValue));
                        break;
                    case 'multiple_locations':
                        $errors = array();
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'Multiple locations value must be an array');
                            continue;
                        }
                        $errors->addError($fieldName, $this->validateMax($dataValue, $fieldData));

                        foreach ($dataValue as $value) {
                            $errors->setErrors($fieldName, $this->validateLocation($value));
                        }

                        break;
                    case 'location_distance':
                        if (!is_array($dataValue)) {
                            $errors->addError($fieldName, 'The location distance value must be an array');
                            continue;
                        }
                        if (!isset($dataValue['distance'])) {
                            $errors->addError($fieldName, 'Distance required');
                        }
                        if (!isset($dataValue['location'])) {
                            $errors->addError($fieldName, 'Location required');
                            continue;
                        }

                        foreach ($this->validateLocation($dataValue['location']) as $error) {
                            $errors->addError($fieldName, $error);
                        }
                        break;
                    case 'email':
                        if (!filter_var($dataValue, FILTER_VALIDATE_EMAIL)) {
                            $errors->addError($fieldName, 'Value must be a valid email');
                        }
                        break;
                    case 'url':
                        if (!filter_var($dataValue, FILTER_VALIDATE_URL)) {
                            $errors->addError($fieldName, 'Value must be a valid URL');
                        }
                        break;
                    case 'image_path':
                        if (!preg_match('/^[\w\/\\-]+\.(png|jpe?g|gif|tiff)$/i', $dataValue)) {
                            $errors->addError($fieldName, 'Value must be a valid path');
                        }
                        break;
                    case 'timestamp':
                        if (!(is_int($dataValue) || is_double($dataValue))) {
                            $errors->addError($fieldName, 'Value must be a valid timestamp');
                        }
                        break;
                    case 'string':
                        $errors->addError($fieldName, $this->validateString($dataValue));
                        break;
                    case 'order':
                        $orderChoices = array('similarity', 'matching');
                        $errors->addError($fieldName, $this->validateChoice($dataValue, $orderChoices));
                        break;
                    case 'multiple_fields':
                        $internalMetadata = $fieldData['metadata'];
                        foreach ($dataValue as $multiData) {
                            $this->validateMetadata($multiData, $internalMetadata, $dataChoices);
                        }
                        break;
                    default:
                        break;
                }
            } else {
                if (isset($fieldData['required']) && $fieldData['required'] === true) {
                    $errors->addError($fieldName, 'It\'s required.');
                }
            }
        }

        $this->throwException($errors);

        return true;
    }

    /**
     * @param ErrorList $errorList
     */
    protected function throwException(ErrorList $errorList)
    {
        if ($errorList->hasErrors()){
            throw new ValidationException($errorList);
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

    protected function validateMax($value, $fieldData, $forceMax = null)
    {
        if (!isset($fieldData['max']) && $forceMax === null) {
            return null;
        }

        $max = $forceMax !== null ? $forceMax : $fieldData['max'];
        if (count($value) > $max) {
            return sprintf('Option length "%s" is too long. "%s" is the maximum', count($value), $max);
        }

        return null;
    }

    protected function validateMin(array $value, $fieldData, $forceMin = null)
    {
        if (!isset($fieldData['min']) && $forceMin === null) {
            return null;
        }

        $min = $forceMin !== null ? $forceMin : $fieldData['min'];
        if (count($value) < $min) {
            return sprintf('Option length "%s" is too short. "%s" is the minimum', count($value), $min);
        }

        return null;
    }

    protected function validateChoice($choice, array $validChoices)
    {
        if (!in_array($choice, $validChoices)) {
            return sprintf('Option with value "%s" is not valid, possible values are "%s"', $choice, implode("', '", $validChoices));
        }

        return null;
    }

    protected function validateDateFormat($dataValue)
    {
        $date = \DateTime::createFromFormat('Y-m-d', $dataValue);

        if (!($date && $date->format('Y-m-d') == $dataValue)) {
            return ('Invalid date format, valid format is "Y-m-d".');
        }

        return null;
    }

    protected function validateString($dataValue)
    {
        if (!is_string($dataValue)) {
            return 'Value must be a string';
        }

        return null;
    }

}