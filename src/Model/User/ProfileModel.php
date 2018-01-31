<?php

namespace Model\User;

use Event\ProfileEvent;
use Model\Metadata\MetadataUtilities;
use Model\Metadata\ProfileMetadataManager;
use Model\Neo4j\GraphManager;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Exception\ValidationException;
use Service\Validator\ProfileValidator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileModel
{
    const MAX_TAGS_AND_CHOICE_LENGTH = 15;
    protected $gm;
    protected $profileOptionManager;
    protected $profileTagManager;
    protected $profileMetadataManager;
    protected $metadataUtilities;
    protected $dispatcher;
    protected $validator;

    public function __construct(GraphManager $gm, ProfileMetadataManager $profileMetadataManager, ProfileOptionManager $profileOptionManager, ProfileTagModel $profileTagModel, MetadataUtilities $metadataUtilities, EventDispatcher $dispatcher, ProfileValidator $validator)
    {
        $this->gm = $gm;
        $this->profileMetadataManager = $profileMetadataManager;
        $this->profileOptionManager = $profileOptionManager;
        $this->profileTagManager = $profileTagModel;
        $this->metadataUtilities = $metadataUtilities;
        $this->dispatcher = $dispatcher;
        $this->validator = $validator;
    }

    /**
     * @param int $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function getById($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:PROFILE_OF]-(profile:Profile)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(profile)<-[optionOf:OPTION_OF]-(option:ProfileOption)')
            ->with('profile', 'collect(distinct {option: option, detail: (CASE WHEN EXISTS(optionOf.detail) THEN optionOf.detail ELSE null END)}) AS options')
            ->optionalMatch('(profile)<-[tagged:TAGGED]-(tag:ProfileTag)-[:TEXT_OF]-(text:TextLanguage)')
            ->with('profile', 'options', 'collect(distinct {tag: tag, tagged: tagged, text: text}) AS tags')
            ->optionalMatch('(profile)-[:LOCATION]->(location:Location)')
            ->returns('profile', 'options', 'tags', 'location')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Profile not found');
        }

        /* @var $row Row */
        $row = $result->current();

        return $this->build($row);
    }

    /**
     * @param $id
     * @param array $data
     * @return array
     * @throws NotFoundHttpException|MethodNotAllowedHttpException
     */
    public function create($id, array $data)
    {
        $this->validateOnCreate($data, $id);

        list($userNode, $profileNode) = $this->getUserAndProfileNodesById($id);

        if (!($userNode instanceof Node)) {
            throw new NotFoundHttpException('User not found');
        }

        if ($profileNode instanceof Node) {
            throw new MethodNotAllowedHttpException(array('PUT'), 'Profile already exists');
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (int)$id)
            ->merge('(profile:Profile)-[po:PROFILE_OF]->(user)');

        $qb->getQuery()->getResultSet();

        $this->saveProfileData($id, $data);

        $profile = $this->getById($id);
        $this->dispatcher->dispatch(\AppEvents::PROFILE_CREATED, (new ProfileEvent($profile, $id)));

        return $profile;
    }

    /**
     * @param integer $id
     * @param array $data
     * @return array
     * @throws ValidationException|NotFoundHttpException
     */
    public function update($id, array $data)
    {
        $this->validateOnUpdate($data, $id);

        list($userNode, $profileNode) = $this->getUserAndProfileNodesById($id);

        if (!($userNode instanceof Node)) {
            throw new NotFoundHttpException('User not found');
        }

        if (!($profileNode instanceof Node)) {
            throw new NotFoundHttpException('Profile not found');
        }

        $this->saveProfileData($id, $data);

        return $this->getById($id);
    }

    /**
     * @param $id
     */
    public function remove($id)
    {
        $this->validateOnRemove($id);

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:PROFILE_OF]-(profile:Profile)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(profile)-[r]-()')
            ->delete('r, profile');

        $query = $qb->getQuery();

        $query->getResultSet();
    }

    /**
     * @param array $data
     * @param $userId
     */
    public function validateOnCreate(array $data, $userId = null)
    {
        $data['userId'] = $userId;
        $data['choices'] = $this->profileOptionManager->getOptions();

        $this->validator->validateOnCreate($data);
    }

    public function validateOnUpdate(array $data, $userId)
    {
        $data['userId'] = $userId;
        $data['choices'] = $this->profileOptionManager->getOptions();
        $this->validator->validateOnUpdate($data);
    }

    public function validateOnRemove($userId)
    {
        $data = array('userId' => $userId);
        $this->validator->validateOnDelete($data);
    }

    public function build(Row $row)
    {
        /* @var $node Node */
        $node = $row->offsetGet('profile');
        $profile = $node->getProperties();
        /* @var $location Node */
        $location = $row->offsetGet('location');
        if ($location && count($location->getProperties()) > 0) {
            $profile['location'] = $location->getProperties();
            if (isset($profile['location']['locality']) && $profile['location']['locality'] === 'N/A') {
                $profile['location']['locality'] = $profile['location']['address'];
            }
        } else {
            $location = null;
        }

        $profile += $this->profileOptionManager->buildOptions($row->offsetGet('options'));
        $profile += $this->profileOptionManager->buildTags($row);

        return $profile;
    }

    protected function getUserAndProfileNodesById($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->optionalMatch('(user)<-[:PROFILE_OF]-(profile:Profile)')
            ->setParameter('id', $id)
            ->returns('user', 'profile')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        if (count($result) < 1) {
            return array(null, null);
        }

        /** @var Row $row */
        $row = $result->current();
        $userNode = $row->offsetGet('user');
        $profileNode = $row->offsetGet('profile');

        return array($userNode, $profileNode);
    }

    //TODO: Divide in saveProfileData and saveProfileOptionsData and saveProfileTagsData
    protected function saveProfileData($id, array $data)
    {
        $metadata = $this->profileMetadataManager->getMetadata();
        $currentOptions = $this->profileOptionManager->getUserProfileOptions($id);
        $tags = $this->profileOptionManager->getUserProfileTags($id);

        if (isset($data['objective']) && in_array('human-contact', $data['objective'])) {
            $data['orientationRequired'] = true;
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$id)
            ->with('profile');
        foreach ($data as $fieldName => $fieldValue) {
            if (isset($metadata[$fieldName])) {

                $fieldType = $metadata[$fieldName]['type'];
                $editable = isset($metadata[$fieldName]['editable']) ? $metadata[$fieldName]['editable'] === true : true;

                if (!$editable) {
                    continue;
                }

                switch ($fieldType) {
                    case 'text':
                    case 'textarea':
                    case 'date':
                    case 'boolean':
                    case 'integer':
                        $qb->set('profile.' . $fieldName . ' = { ' . $fieldName . ' }')
                            ->setParameter($fieldName, $fieldValue)
                            ->with('profile');
                        break;
                    case 'birthday':
                        $zodiacSign = $this->metadataUtilities->getZodiacSignFromDate($fieldValue);
                        if (isset($currentOptions['zodiacSign'])) {
                            $qb->match('(profile)<-[zodiacSignRel:OPTION_OF]-(zs:ZodiacSign)')
                                ->delete('zodiacSignRel')
                                ->with('profile');
                        }
                        if (!is_null($zodiacSign)) {
                            $qb->match('(newZs:ZodiacSign {id: { zodiacSign }})')
                                ->merge('(profile)<-[:OPTION_OF]-(newZs)')
                                ->setParameter('zodiacSign', $zodiacSign)
                                ->with('profile');
                        }

                        $qb->set('profile.' . $fieldName . ' = { birthday }')
                            ->setParameter('birthday', $fieldValue)
                            ->with('profile');
                        break;
                    case 'location':
                        $qb->optionalMatch('(profile)-[rLocation:LOCATION]->(oldLocation:Location)')
                            ->delete('rLocation', 'oldLocation')
                            ->with('profile');

                        $qb->create('(location:Location {latitude: { latitude }, longitude: { longitude }, address: { address }, locality: { locality }, country: { country }})')
                            ->createUnique('(profile)-[:LOCATION]->(location)')
                            ->setParameter('latitude', $fieldValue['latitude'])
                            ->setParameter('longitude', $fieldValue['longitude'])
                            ->setParameter('address', $fieldValue['address'])
                            ->setParameter('locality', $fieldValue['locality'])
                            ->setParameter('country', $fieldValue['country'])
                            ->with('profile');
                        break;
                    case 'choice':
                        if (isset($currentOptions[$fieldName])) {
                            $qb->optionalMatch('(profile)<-[optionRel:OPTION_OF]-(:' . $this->metadataUtilities->typeToLabel($fieldName) . ')')
                                ->delete('optionRel')
                                ->with('profile');
                        }
                        if (!is_null($fieldValue)) {
                            $qb->match('(option:' . $this->metadataUtilities->typeToLabel($fieldName) . ' {id: { ' . $fieldName . ' }})')
                                ->merge('(profile)<-[:OPTION_OF]-(option)')
                                ->setParameter($fieldName, $fieldValue)
                                ->with('profile');
                        }
                        break;
                    case 'double_choice':
                        $qbDoubleChoice = $this->gm->createQueryBuilder();
                        $qbDoubleChoice->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
                            ->where('u.qnoow_id = { id }')
                            ->setParameter('id', (int)$id)
                            ->with('profile');

                        if (isset($currentOptions[$fieldName])) {
                            $qbDoubleChoice->optionalMatch('(profile)<-[doubleChoiceOptionRel:OPTION_OF]-(:' . $this->metadataUtilities->typeToLabel($fieldName) . ')')
                                ->delete('doubleChoiceOptionRel')
                                ->with('profile');
                        }
                        if (isset($fieldValue['choice'])) {
                            $detail = !is_null($fieldValue['detail']) ? $fieldValue['detail'] : '';
                            $qbDoubleChoice->match('(option:' . $this->metadataUtilities->typeToLabel($fieldName) . ' {id: { ' . $fieldName . ' }})')
                                ->merge('(profile)<-[:OPTION_OF {detail: {' . $fieldName . '_detail}}]-(option)')
                                ->setParameter($fieldName, $fieldValue['choice'])
                                ->setParameter($fieldName . '_detail', $detail);
                        }
                        $qbDoubleChoice->returns('profile');

                        $query = $qbDoubleChoice->getQuery();
                        $query->getResultSet();

                        break;
                    case 'tags_and_choice':
                        if (is_array($fieldValue)) {
                            $tagLabel = $this->metadataUtilities->typeToLabel($fieldName);
                            $interfaceLanguage = $this->getInterfaceLocale($id);
                            $this->profileTagManager->setTagsAndChoice($id, $interfaceLanguage, $fieldName, $tagLabel, $fieldValue);
                        }

                        break;
                    case 'multiple_choices':
                        $qbMultipleChoices = $this->gm->createQueryBuilder();
                        $qbMultipleChoices->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
                            ->where('u.qnoow_id = { id }')
                            ->setParameter('id', (int)$id)
                            ->with('profile');

                        if (isset($currentOptions[$fieldName])) {
                            $qbMultipleChoices->optionalMatch('(profile)<-[optionRel:OPTION_OF]-(:' . $this->metadataUtilities->typeToLabel($fieldName) . ')')
                                ->delete('optionRel')
                                ->with('profile');
                        }
                        if (is_array($fieldValue)) {
                            foreach ($fieldValue as $index => $value) {
                                $qbMultipleChoices->match('(option:' . $this->metadataUtilities->typeToLabel($fieldName) . ' {id: { ' . $index . ' }})')
                                    ->merge('(profile)<-[:OPTION_OF]-(option)')
                                    ->setParameter($index, $value)
                                    ->with('profile');
                            }
                        }
                        $qbMultipleChoices->returns('profile');

                        $query = $qbMultipleChoices->getQuery();
                        $query->getResultSet();
                        break;
                    case 'tags':
                        $tagLabel = $this->metadataUtilities->typeToLabel($fieldName);

                        if (isset($tags[$fieldName])) {
                            $this->profileTagManager->deleteTagRelationships($id, $tagLabel, $tags[$fieldName]);
                        }

                        if (is_array($fieldValue) && !empty($fieldValue)) {
                            $interfaceLanguage = $this->getInterfaceLocale($id);
                            $this->profileTagManager->addTags($id, $interfaceLanguage, $tagLabel, $fieldValue);
                        }

                        break;
                }
            }
        }

        $qb->optionalMatch('(profile)<-[optionOf:OPTION_OF]-(option:ProfileOption)')
            ->optionalMatch('(profile)<-[tagged:TAGGED]-(tag:ProfileTag)')
            ->returns('profile', 'collect(distinct {option: option, detail: (CASE WHEN EXISTS(optionOf.detail) THEN optionOf.detail ELSE null END)}) AS options', 'collect(distinct {tag: tag, tagged: tagged}) AS tags')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        return $this->build($result->current());
    }

    public function getIndustryIdFromDescription($description)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(industry:ProfileOption:Industry)')
            ->where('industry.name_en = {description}')
            ->setParameter('description', $description)
            ->returns('industry.id as id')
            ->limit(1);

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        /** @var Row $row */
        $row = $result->current();
        if ($row->offsetExists('id')) {
            return $row->offsetGet('id');
        }

        throw new NotFoundHttpException(sprintf("Description %s not found", $description));
    }

    protected function getInterfaceLocale($userId)
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(profile:Profile)-[:PROFILE_OF]->(u:User)')
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$userId)
            ->with('profile');

        $qb->match('(profile)-[:OPTION_OF]-(i:InterfaceLanguage)')
            ->returns('i.id AS locale');

        $result = $qb->getQuery()->getResultSet();

        return $result->current()->offsetGet('locale');
    }
}