<?php
/**
 * @author yawmoght <yawmoght@gmail.com>
 */

namespace Model\User\Filters;


class FilterUsers implements \JsonSerializable
{

    protected $metadata = array();

    protected $profileFilters = array();
    protected $usersFilters = array();
    protected $id;

    /**
     * Filters constructor.
     * @param $metadata
     */
    public function __construct($metadata)
    {
        $this->metadata = $metadata;
    }

    public function getProfileFilters()
    {
        return $this->profileFilters;
    }

    public function getUserFilters()
    {
        return $this->usersFilters;
    }

    /**
     * @param array $profileFilters
     */
    public function setProfileFilters($profileFilters)
    {
        $this->profileFilters = $profileFilters;
    }

    /**
     * @param array $usersFilters
     */
    public function setUsersFilters($usersFilters)
    {
        $this->usersFilters = $usersFilters;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return array(
            'profileFilters' => $this->getProfileFilters(),
            'userFilters' => $this->getUserFilters(),
        );

    }
}