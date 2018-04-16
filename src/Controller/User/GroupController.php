<?php

namespace Controller\User;

use Model\User\User;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GroupController
 * @package Controller
 */
class GroupController
{
    /**
     * @param Application $app
     * @param integer $groupId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function getAction(Application $app, $groupId)
    {
        $group = $app['users.groups.model']->getById($groupId);

        return $app->json($group);
    }

    public function postAction(Request $request, Application $app, User $user)
    {
        $data = $request->request->all();

        $data['createdBy'] = $user->getId();
        $createdGroup = $app['group.service']->createGroup($data);
        $app['group.service']->addUser($createdGroup->getId(), $user->getId());

        $data['groupId'] = $createdGroup->getId();
        $invitationData = array(
            'userId' => $user->getId(),
            'groupId' => $createdGroup->getId(),
            'available' => 999999999
        );
        $createdInvitation = $app['users.invitations.model']->create($invitationData);

        $createdGroup->setInvitation($createdInvitation);
        return $app->json($createdGroup, 201);
    }

    public function getContentsAction(Request $request, Application $app, $groupId)
    {
        $paginator = $app['paginator'];
        $groupContentModel = $app['users.group.content.model'];
        $filters = array('groupId' => (int)$groupId);

        $content = $paginator->paginate($filters, $groupContentModel, $request);

        return $app->json($content);
    }

    /**
     * @param Application $app
     * @param User $user
     * @param integer $groupId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function addUserAction(Application $app, User $user, $groupId)
    {
        $group = $app['group.service']->addUser((int)$groupId, $user->getId());

        return $app->json($group);
    }

    /**
     * @param Application $app
     * @param User $user
     * @param integer $groupId
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function removeUserAction(Application $app, User $user, $groupId)
    {
        $removed = $app['group.service']->removeUser($groupId, $user->getId());

        return $app->json($removed, 204);
    }
}