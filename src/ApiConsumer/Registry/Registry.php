<?php

namespace ApiConsumer\Registry;

use Doctrine\ORM\EntityManager;
use Model\Entity\FetchRegistry;

class Registry
{

    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {

        $this->entityManager = $entityManager;
    }

    public function registerFetchAttempt($userId, $resource, $lastItemId = null, $error = false)
    {

        $registryEntry = new FetchRegistry();
        $registryEntry->setUserId($userId);
        $registryEntry->setResource($resource);
        $registryEntry->setLastItemId($lastItemId);

        if ($error) {
            $registryEntry->setStatus($registryEntry::STATUS_ERROR);
        } else {
            $registryEntry->setStatus($registryEntry::STATUS_SUCCESS);
        }

        try {
            $this->entityManager->persist($registryEntry);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
