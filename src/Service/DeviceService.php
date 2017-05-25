<?php

namespace Service;

use GuzzleHttp\Client;
use Model\User\Device\Device;
use Model\User\Device\DeviceModel;
use Model\User\ProfileModel;
use Silex\Translator;

class DeviceService
{
    const MESSAGE_CATEGORY = 'message';
    const PROCESS_FINISH_CATEGORY = 'process_finish';
    const BOTH_USER_LIKED_CATEGORY = 'both_user_liked';
    const GENERIC_CATEGORY = 'generic';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var DeviceModel
     */
    protected $dm;

    /**
     * @var ProfileModel
     */
    protected $pm;

    /**
     * @var Translator
     */
    protected $translator;

    protected $fireBaseUrl;
    protected $fireBaseApiKey;
    protected $serverPublicKey;
    protected $serverPrivateKey;

    public function __construct(Client $client, DeviceModel $dm, ProfileModel $pm, Translator $translator, $fireBaseUrl, $fireBaseApiKey, $serverPublicKey, $serverPrivateKey)
    {
        $this->client = $client;
        $this->dm = $dm;
        $this->pm = $pm;
        $this->translator = $translator;
        $this->fireBaseUrl = $fireBaseUrl;
        $this->fireBaseApiKey = $fireBaseApiKey;
        $this->serverPublicKey = $serverPublicKey;
        $this->serverPrivateKey = $serverPrivateKey;
    }

    public function pushMessage(array $data, $userId, $category = self::GENERIC_CATEGORY)
    {
        $this->validatePushData($category, $data);
        $devices = $this->dm->getAll($userId);
        $profile = $this->pm->getById($userId);

        if (isset($profile['interfaceLanguage']) && $profile['interfaceLanguage']) {
            $this->translator->setLocale($profile['interfaceLanguage']);
        }

        $registrationIds = array();
        /** @var Device $device */
        foreach ($devices as $device) {
            $registrationIds[] = $device->getRegistrationIdFromEndpoint();
        }

        $payload = array(
            "data" => $this->getPayloadData($category, $data),
            "registration_ids" => $registrationIds
        );

        return $this->client->post($this->fireBaseUrl, array(
            'json' => $payload,
            'headers' => array(
                'Authorization' => 'key=' . $this->fireBaseApiKey,
                'Content-Type' => 'application/json',
            ),
        ))->getBody()->getContents();
    }

    private function getPayloadData($category, $data)
    {
        switch ($category) {
            case self::MESSAGE_CATEGORY:
                return array(
                    'title' => $this->translator->trans('push_notifications.message.title', array('%username%' => $data['username'])),
                    'body' => $data['body'],
                    'image' => $data['image'],
                    'image-type' => "circle",
                );
            case self::PROCESS_FINISH_CATEGORY:
                return array(
                    'title' => $this->translator->trans('push_notifications.process_finish.title'),
                    'body' => $this->translator->trans('push_notifications.process_finish.body', array('%resource%' => $data['resource'])),
                );
            case self::BOTH_USER_LIKED_CATEGORY:
                return array(
                    'title' => $this->translator->trans('push_notifications.both_user_liked.title'),
                    'body' => $this->translator->trans('push_notifications.both_user_liked.body', array('%username%' => $data['username'])),
                    'image' => $data['image'],
                    'image-type' => "circle",
                );
            default:
                return array(
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'image' => isset($data['image']) ? $data['image'] : null,
                );
        }
    }

    private function validatePushData($category, $data)
    {
        if (!array_key_exists($category, $this->getValidCategories())) {
            throw new \Exception(sprintf("Category %s does not exist", $category));
        }

        switch ($category) {
            case self::MESSAGE_CATEGORY:
                if (!isset($data['username']) || !isset($data['image']) || !isset($data['username'])) {
                    throw new \Exception("Username is not defined for message category");
                }
                break;
            case self::PROCESS_FINISH_CATEGORY:
                if (!isset($data['resource'])) {
                    throw new \Exception("Resource is not defined for process finish category");
                }
                break;
            case self::BOTH_USER_LIKED_CATEGORY:
                if (!isset($data['username']) || !isset($data['image'])) {
                    throw new \Exception("Username is not defined for both user liked category");
                }
                break;
            case self::GENERIC_CATEGORY:
                if (!isset($data['title']) || !isset($data['body'])) {
                    throw new \Exception("Title or body is not defined for generic category");
                }
                break;
        }
    }

    private function getValidCategories()
    {
        return array(
            self::MESSAGE_CATEGORY,
            self::PROCESS_FINISH_CATEGORY,
            self::BOTH_USER_LIKED_CATEGORY,
            self::GENERIC_CATEGORY,
        );
    }
}