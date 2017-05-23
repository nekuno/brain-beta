<?php

namespace Service;

use GuzzleHttp\Client;
use Model\User\Device\Device;
use Model\User\Device\DeviceModel;

class DeviceService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var DeviceModel
     */
    protected $dm;

    protected $fireBaseApiKey;
    protected $serverPublicKey;
    protected $serverPrivateKey;

    public function __construct(Client $client, DeviceModel $dm, $fireBaseApiKey, $serverPublicKey, $serverPrivateKey)
    {
        $this->client = $client;
        $this->dm = $dm;
        $this->fireBaseApiKey = $fireBaseApiKey;
        $this->serverPublicKey = $serverPublicKey;
        $this->serverPrivateKey = $serverPrivateKey;
    }

    public function pushMessage($title, $text, $userId)
    {
        $devices = $this->dm->getAll($userId);

        $registrationIds = array();
        /** @var Device $device */
        foreach ($devices as $device) {
            $registrationIds[] = $device->getEndpointToken();
        }

        $payload = array(
            'notification' => array(
                'title' => $title,
                'body' => $text,
                'icon' => 'android-chrome-192x192.png',
            ),
            "registration_ids" => $registrationIds
        );

        return $this->client->post("https://fcm.googleapis.com/fcm/send", array(
            'json' => $payload,
            'headers' => array(
                'Authorization' => 'key=' . $this->fireBaseApiKey,
                'Content-Type' => 'application/json',
            ),
        ))->getBody()->getContents();
    }

}