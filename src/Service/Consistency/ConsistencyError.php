<?php

namespace Service\Consistency;

class ConsistencyError
{
    protected $data;
    protected $rule;
    protected $nodeId;
    protected $solved = false;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return ConsistencyNodeRule
     */
    public function getRule()
    {
        return $this->rule;
    }

    /**
     * @param mixed $rule
     */
    public function setRule(ConsistencyNodeRule $rule)
    {
        $this->rule = $rule;
    }

    /**
     * @return mixed
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * @param mixed $nodeId
     */
    public function setNodeId($nodeId)
    {
        $this->nodeId = $nodeId;
    }

    /**
     * @return bool
     */
    public function isSolved()
    {
        return $this->solved;
    }

    /**
     * @param bool $solved
     */
    public function setSolved($solved)
    {
        $this->solved = $solved;
    }

    public function getMessage()
    {
        return 'Error with data ' . json_encode($this->data);
    }
}