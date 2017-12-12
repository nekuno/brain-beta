<?php

namespace Service\Consistency\ConsistencyErrors;

class MissingPropertyConsistencyError extends ConsistencyError
{
    protected $propertyName;

    /**
     * @return mixed
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @param mixed $propertyName
     */
    public function setPropertyName($propertyName)
    {
        $this->propertyName = $propertyName;
    }

    public function getDefaultProperty()
    {
        $rule = $this->getRule();
        $propertyRule = $rule->getProperties()[$this->getPropertyName()];

        return isset($propertyRule['default']) ? $propertyRule['default'] : null;
    }

    public function getMessage()
    {
        return sprintf('Missing property %s on node %d', $this->propertyName, $this->nodeId);
    }

}