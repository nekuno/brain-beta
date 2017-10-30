<?php

namespace Service;

use Model\Metadata\MetadataManager;
use Model\Metadata\MetadataManagerFactory;
use Model\Metadata\MetadataUtilities;
use Model\Metadata\UserFilterMetadataManager;
use Model\User\Group\GroupModel;
use Model\User\ProfileOptionManager;

class MetadataService
{
    protected $metadataManagerFactory;
    protected $groupModel;
    protected $profileOptionManager;
    protected $metadataUtilities;

    protected $managers = array();

    /**
     * MetadataService constructor.
     * @param MetadataManagerFactory $metadataManagerFactory
     * @param GroupModel $groupModel
     * @param ProfileOptionManager $profileOptionManager
     * @param MetadataUtilities $metadataUtilities
     */
    public function __construct(MetadataManagerFactory $metadataManagerFactory, GroupModel $groupModel, ProfileOptionManager $profileOptionManager, MetadataUtilities $metadataUtilities)
    {
        $this->metadataManagerFactory = $metadataManagerFactory;
        $this->groupModel = $groupModel;
        $this->profileOptionManager = $profileOptionManager;
        $this->metadataUtilities = $metadataUtilities;
    }

    public function getUserFilterMetadata($locale, $userId = null)
    {
        $metadata = $this->getBasicMetadata($locale, 'user_filter');

        $choices = $this->profileOptionManager->getLocaleOptions($locale);
        $metadata = $this->addChoices($metadata, $choices, $locale);

        if ($userId){
            $groupChoices = $this->getGroupChoices($userId);
            if (!empty($groupChoices)){
                $metadata['groups'] = $groupChoices;
            }
        }

        return $metadata;
    }

    public function getProfileMetadata($locale = null)
    {
        $metadata = $this->getBasicMetadata($locale, 'profile');

        $choices = $this->profileOptionManager->getOptions();
        $metadata = $this->addChoices($metadata, $choices, $locale);

        return $metadata;
    }

    public function getCategoriesMetadata($locale = null)
    {
        $metadata = $this->getBasicMetadata($locale, 'categories');

        return $metadata;
    }

    public function getContentFilterMetadata($locale = null)
    {
        $metadata = $this->getBasicMetadata($locale, 'content_filter');

        return $metadata;
    }

    public function getGroupChoices($userId)
    {
        $groups = $this->groupModel->getAllByUserId($userId);
        $choices = $this->groupModel->buildGroupNames($groups);

        return $choices;
    }

    protected function getMetadataManager($name)
    {
        if (isset($this->managers[$name])){
            return $this->managers[$name];
        }

        $manager = $this->metadataManagerFactory->build($name);
        $this->managers[$name] = $manager;

        return $manager;
    }

    protected function addChoices(array $metadata, array $choices, $locale)
    {
        foreach ($metadata as $name => &$field)
        {
            switch ($field['type']) {
                case 'choice':
                    $field = $this->addSingleChoices($field, $name, $choices);
                    foreach ($field['choices'] as $choice)
                    {
                        $this->fixLocale($choice, $locale);
                    }
                    break;
                case 'double_choice':
                case 'double_multiple_choices':
                case 'choice_and_multiple_choices':
                    $field = $this->addSingleChoices($field, $name, $choices);
//                    foreach ($field[])
                        $field = $this->fixDoubleChoicesLocale($field, $locale);
                    break;
                case 'multiple_choices':
                    $field = $this->addSingleChoices($field, $name, $choices);
                    $field['max_choices'] = isset($field['max_choices']) ? $field['max_choices'] : 999;
                    $field['min_choices'] = isset($field['min_choices']) ? $field['min_choices'] : 0;
                    break;
                case 'tags_and_choice':
                    $field['choices'] = array();
                    if (isset($field['choices'])) {
                        foreach ($field['choices'] as $choice => $description) {
                            $field['choices'][$choice] = $this->metadataUtilities->getLocaleString($description, $locale);
                        }
                    }
                    $field['top'] = $this->profileOptionManager->getTopProfileTags($name);
                    break;
                case 'tags':
                    $field['top'] = $this->profileOptionManager->getTopProfileTags($name);
                    break;
                default:
                    break;
            }
        }

        return $metadata;
    }

    /**
     * @param $field
     * @param $name
     * @param $choices
     * @return array
     */
    protected function addSingleChoices(array $field, $name, $choices)
    {
        $field['choices'] = isset($choices[$name]) ? $choices[$name] : array();

        return $field;
    }

    /**
     * @param $field
     * @param $locale
     * @return mixed
     */
    protected function fixDoubleChoicesLocale($field, $locale)
    {
        $valueDoubleChoices = isset($field['doubleChoices']) ? $field['doubleChoices'] : array();
        foreach ($valueDoubleChoices as $choice => $doubleChoices) {
            foreach ($doubleChoices as $doubleChoice => $doubleChoiceValues) {
                $field['doubleChoices'][$choice][$doubleChoice] = $this->metadataUtilities->getLocaleString($doubleChoiceValues, $locale);
            }
        }

        return $field;
    }

    protected function fixLocale($choices, $locale)
    {
//        foreach ($choices)
    }

    protected function getBasicMetadata($locale, $name)
    {
        /** @var UserFilterMetadataManager $userFilterMetadataManager */
        $userFilterMetadataManager = $this->getMetadataManager($name);
        $metadata = $userFilterMetadataManager->getMetadata($locale);

        return $metadata;
    }

}