<?php

namespace Controller;

use Model\User;
use Silex\ControllerResolver as BaseControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Adds User as a valid argument for controllers.
 */
class ControllerResolver extends BaseControllerResolver
{
    protected function doGetArguments(Request $request, $controller, array $parameters)
    {
        foreach ($parameters as $param) {
            /* @var $param \ReflectionParameter */
            if ($param->getClass() && $param->getClass()->isSubclassOf('Symfony\Component\Security\Core\User\UserInterface')) {

                if (!$this->app['user']) {
                    if (is_array($controller)) {
                        $repr = sprintf('%s::%s()', get_class($controller[0]), $controller[1]);
                    } elseif (is_object($controller)) {
                        $repr = get_class($controller);
                    } else {
                        $repr = $controller;
                    }
                    throw new \RuntimeException(sprintf('Controller "%s" uses User "$%s" but user is not authenticated (missing jwt).', $repr, $param->name));
                }

                $this->checkOwnUser($request);

                $request->attributes->set($param->getName(), $this->app['user']);

                break;
            }
        }

        $this->checkOtherUser($request);

        return parent::doGetArguments($request, $controller, $parameters);
    }

    protected function checkOtherUser(Request $request)
    {
        list($otherUserId, $otherUser) = $this->getUser($request);

        if ($otherUser instanceof User && !$otherUser->isEnabled()){
            //TODO: refactor to userManager->manageUserNotFound
            throw new NotFoundHttpException(sprintf('User "%s" not found', $otherUserId));
        }
    }

    protected function getUser(Request $request)
    {
        $attributes = $request->attributes;
        $userManager = $this->app['users.manager'];

        $otherUser = null;
        $otherUserId = null;
        if ($attributes->get('userId')){
            $otherUserId= $attributes->get('userId');
            $otherUser = $userManager->getById($otherUserId);
        }
        if ($attributes->get('to')){
            $otherUserId = $attributes->get('to');
            $otherUser = $userManager->getById($otherUserId);
        }
        if ($attributes->get('from')){
            $otherUserId = $attributes->get('from');
            $otherUser = $userManager->getById($otherUserId);
        }
        if ($attributes->get('slug')){
            $otherUserId = $attributes->get('slug');
            $otherUser = $userManager->getBySlug($otherUserId);
        }

        return array($otherUserId, $otherUser);
    }

    protected function checkOwnUser(Request $request)
    {
        if (!$this->isUserCorrect($this->app['user'], $request->getPathInfo())) {
            throw new AuthenticationException('User is disabled');
        }
    }

    protected function isUserCorrect(User $user, $path)
    {
        $excludedPaths = array('/users/enable', '/users');
        $mustCheckEnabled = !in_array($path, $excludedPaths);

        if ($mustCheckEnabled && !$user->isEnabled()) {
            return false;
        }

        return true;
    }
}