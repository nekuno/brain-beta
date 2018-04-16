<?php

namespace Controller;

use Silex\Application;
use Model\User\User;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Adds User as a valid argument for controllers.
 */
class ArgumentValueResolver implements ArgumentValueResolverInterface
{
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if (User::class !== $argument->getType()) {
            return false;
        }

        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $this->checkOwnUser($request);
        $this->checkOtherUser($request);

        yield $this->app['user'];
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

    protected function isUserCorrect(User $user = null, $path)
    {
        $excludedPaths = array('/users/enable', '/users');
        $mustCheckEnabled = !in_array($path, $excludedPaths);

        if (!$user || $mustCheckEnabled && !$user->isEnabled()) {
            return false;
        }

        return true;
    }
}