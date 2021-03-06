<?php


namespace Model\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * @Entity(repositoryClass="DataStatusRepository")
 * @Table(name="data_status")
 * @HasLifecycleCallbacks()
 */
class DataStatus
{

    /**
     * @Id()
     * @Column(name="user_id", type="integer")
     */
    protected $userId;

    /**
     * @Id()
     * @Column(name="resourceOwner", type="string")
     */
    protected $resourceOwner;

    /**
     * @Column(name="fetched", type="boolean", nullable=false)
     */
    protected $fetched = false;

    /**
     * @Column(name="processed", type="boolean", nullable=false)
     */
    protected $processed = false;

    /**
     * @Column(name="update_at", type="datetime")
     */
    protected $updateAt;

    /**
     * Get user
     *
     * @return integer
     */
    public function getUserId()
    {

        return $this->userId;
    }

    /**
     * Set user
     *
     * @param integer $user
     * @return DataStatus
     */
    public function setUserId($user)
    {

        $this->userId = $user;

        return $this;
    }

    /**
     * Get resourceOwner
     *
     * @return string
     */
    public function getResourceOwner()
    {

        return $this->resourceOwner;
    }

    /**
     * Set resourceOwner
     *
     * @param string $resourceOwner
     * @return DataStatus
     */
    public function setResourceOwner($resourceOwner)
    {

        $this->resourceOwner = $resourceOwner;

        return $this;
    }

    /**
     * Get fetched
     *
     * @return boolean
     */
    public function getFetched()
    {

        return $this->fetched;
    }

    /**
     * Set fetched
     *
     * @param boolean $fetched
     * @return DataStatus
     */
    public function setFetched($fetched)
    {

        $this->fetched = $fetched;

        return $this;
    }

    /**
     * Get processed
     *
     * @return boolean
     */
    public function getProcessed()
    {

        return $this->processed;
    }

    /**
     * Set processed
     *
     * @param boolean $processed
     * @return DataStatus
     */
    public function setProcessed($processed)
    {

        $this->processed = $processed;

        return $this;
    }

    /**
     * Get updateAt
     *
     * @return \DateTime
     */
    public function getUpdateAt()
    {

        return $this->updateAt;
    }

    /**
     * @prePersist
     */
    public function setUpdateAt()
    {

        $this->updateAt = new \DateTime();
    }
}
