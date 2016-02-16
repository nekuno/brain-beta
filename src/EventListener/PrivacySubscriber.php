<?php
/**
 * @author yawmoght <yawmoght@gmail.com>
 */

namespace EventListener;

use Event\PrivacyEvent;
use Model\User\GroupModel;
use Model\User\InvitationModel;
use Model\User\ProfileModel;
use Model\UserModel;
use Silex\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PrivacySubscriber implements EventSubscriberInterface
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var GroupModel
     */
    protected $groupModel;

    /**
     * @var InvitationModel
     */
    protected $invitationModel;

    /**
     * @var UserModel
     */
    protected $userModel;

    /**
     * @var string
     */
    protected $socialhost;

    /**
     * @var ProfileModel
     */
    protected $profileModel;

    /**
     * PrivacySubscriber constructor.
     * @param Translator $translator
     * @param GroupModel $groupModel
     * @param UserModel $userModel
     * @param ProfileModel $profileModel
     * @param InvitationModel $invitationModel
     * @param $socialhost
     */
    public function __construct(Translator $translator, GroupModel $groupModel, UserModel $userModel, ProfileModel $profileModel, InvitationModel $invitationModel, $socialhost)
    {
        $this->translator = $translator;
        $this->groupModel = $groupModel;
        $this->userModel = $userModel;
        $this->profileModel = $profileModel;
        $this->invitationModel = $invitationModel;
        $this->socialhost = $socialhost;
    }

    public static function getSubscribedEvents()
    {
        return array(
            \AppEvents::PRIVACY_UPDATED => array('onPrivacyUpdated'),
        );
    }

    public function onPrivacyUpdated(PrivacyEvent $event)
    {
        $data = $event->getPrivacy();

        $groupsFollowers = $this->groupModel->getGroupFollowersFromInfluencerId($event->getUserId());

        if ($this->needsGroupFollowers($data)) {
            $influencer = $this->userModel->getById($event->getUserId());
            $influencerProfile = $this->profileModel->getById($event->getUserId());
            if (isset($influencerProfile['interfaceLanguage'])) {
                $this->translator->setLocale($influencerProfile['interfaceLanguage']);
            }

            $groupName = $this->translator->trans('followers.group_name', array('%username%' => $influencer['username']));
            $typeMatching = str_replace("min_", "", $data['messages']['key']);
            $groupData = array(
                'date' => 4102444799000,
                'name' => $groupName,
                'html' => '<h3> ' . $groupName . ' </h3>',
                'location' => array(
                    'latitude' => 40.4167754,
                    'longitude' => -3.7037902,
                    'address' => 'Madrid',
                    'locality' => 'Madrid',
                    'country' => 'Spain'
                ),
                'followers' => true,
                'influencer_id' => $event->getUserId(),
                'min_matching' => $data['messages']['value'],
                'type_matching' => $typeMatching,
            );

            if (isset($groupsFollowers[0])) {
                $groupId = $groupsFollowers[0];
                $group = $this->groupModel->update($groupId, $groupData);
                $this->invitationModel->setAvailableInvitations($group['invitation_token'], InvitationModel::MAX_AVAILABLE);
            } else {
                $group = $this->groupModel->create($groupData);
                $picture = isset($influencer['picture']) ?
                    'media/cache/resolve/user_avatar_180x180/user/images/' . $influencer['picture']
                    : 'media/cache/user_avatar_180x180/bundles/qnoowweb/images/user-no-img.jpg';
                $compatibleOrSimilar = $typeMatching === 'compatibility' ? 'compatible' : 'similar';
                $slogan = $this->translator->trans('followers.invitation_slogan', array('%username%' => $influencer['username'], '%compatible_or_similar%' => $compatibleOrSimilar));
                $invitationData = array(
                    'userId' => $event->getUserId(),
                    'groupId' => $group['id'],
                    'available' => InvitationModel::MAX_AVAILABLE,
                    'slogan' => $slogan,
                    'image_url' => $this->socialhost . $picture,
                );

                $this->invitationModel->create($invitationData);
            }

        } else {
            if (isset($groupsFollowers[0])) {

                $groupId = $groupsFollowers[0];
                $invitation = $this->invitationModel->getByGroupFollowersId($groupId);
                if ($invitation['invitation']['available'] > 0) {
                    $this->invitationModel->setAvailableInvitations($invitation['invitation']['token'], 0);
                }

            }
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    protected function needsGroupFollowers(array $data)
    {
        if (isset($data['messages']['key']) && isset($data['messages']['value'])) {
            if (in_array($data['messages']['key'], array('min_compatibility', 'min_similarity'))) {
                if (isset($data['messages']['value'])) {
                    return true;
                }
            }
        }

        return false;
    }
}