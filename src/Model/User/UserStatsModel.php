<?php

namespace Model\User;


class UserStatsModel
{

    protected $numberOfContentLikes;

    protected $numberOfVideoLikes;

    protected $numberOfAudioLikes;

    protected $numberOfImageLikes;

    protected $numberOfReceivedLikes;

    protected $numberOfUserLikes;

    protected $groupsBelonged;

    protected $numberOfQuestionsAnswered;

    protected $available_invitations;

    function __construct($numberOfContentLikes,
                         $numberOfVideoLikes,
                         $numberOfAudioLikes,
                         $numberOfImageLikes,
                         $numberOfReceivedLikes,
                         $numberOfUserLikes,
                         $groupsBelonged,
                         $numberOfQuestionsAnswered,
                         $available_invitations)
    {
        $this->numberOfContentLikes = $numberOfContentLikes;
        $this->numberOfVideoLikes = $numberOfVideoLikes;
        $this->numberOfAudioLikes = $numberOfAudioLikes;
        $this->numberOfImageLikes = $numberOfImageLikes;
        $this->numberOfReceivedLikes = $numberOfReceivedLikes;
        $this->numberOfUserLikes = $numberOfUserLikes;
        $this->groupsBelonged = $groupsBelonged;
        $this->numberOfQuestionsAnswered = $numberOfQuestionsAnswered;
        $this->available_invitations = $available_invitations;
    }

    /**
     * @return mixed
     */
    public function getNumberOfContentLikes()
    {
        return $this->numberOfContentLikes;
    }

    /**
     * @return mixed
     */
    public function getNumberOfVideoLikes()
    {
        return $this->numberOfVideoLikes;
    }

    /**
     * @return mixed
     */
    public function getNumberOfAudioLikes()
    {
        return $this->numberOfAudioLikes;
    }

    /**
     * @return mixed
     */
    public function getNumberOfImageLikes()
    {
        return $this->numberOfImageLikes;
    }

    /**
     * @return mixed
     */
    public function getNumberOfReceivedLikes()
    {
        return $this->numberOfReceivedLikes;
    }

    /**
     * @return mixed
     */
    public function getNumberOfUserLikes()
    {
        return $this->numberOfUserLikes;
    }

    /**
     * @return mixed
     */
    public function getGroupsBelonged()
    {
        return $this->groupsBelonged;
    }

    /**
     * @return mixed
     */
    public function getNumberOfQuestionsAnswered()
    {
        return $this->numberOfQuestionsAnswered;
    }

    /**
     * @return integer
     */
    public function getAvailableInvitations()
    {
        return $this->available_invitations;
    }

    public function toArray(){
        return array('numberOfContentLikes' => $this->numberOfContentLikes,
                     'numberOfVideoLikes' => $this->numberOfVideoLikes,
                     'numberOfAudioLikes' => $this->numberOfAudioLikes,
                     'numberOfImageLikes' => $this->numberOfImageLikes,
                     'numberOfReceivedLikes' => $this->numberOfReceivedLikes,
                     'numberOfUserLikes' => $this->numberOfUserLikes,
                     'groupsBelonged' => $this->groupsBelonged,
                     'numberOfQuestionsAnswered' => $this->numberOfQuestionsAnswered,
                     'available_invitations' => $this->available_invitations,
        );
    }

}