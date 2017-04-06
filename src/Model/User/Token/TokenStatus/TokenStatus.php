<?php

namespace Model\User\Token\TokenStatus;

class TokenStatus
{
    protected $fetched = 0;

    protected $processed = 0;

    protected $updatedAt;

    /**
     * @return mixed
     */
    public function getFetched()
    {
        return $this->fetched;
    }

    /**
     * @param mixed $fetched
     */
    public function setFetched($fetched)
    {
        $this->fetched = $fetched;
    }

    /**
     * @return mixed
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    /**
     * @param mixed $processed
     */
    public function setProcessed($processed)
    {
        $this->processed = $processed;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

}