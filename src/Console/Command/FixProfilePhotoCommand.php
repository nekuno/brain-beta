<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\User\Photo\PhotoManager;
use Manager\UserManager;
use Model\User\Photo\Photo;
use Model\User;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixProfilePhotoCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('users:fix-profile-photo')
            ->setDescription('Fix profile photos in gallery.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $usersManager UserManager */
        $usersManager = $this->app['users.manager'];
        /* @var $photoManager PhotoManager */
        $photoManager = $this->app['users.photo.manager'];

        $users = $usersManager->getAll();
        $fixed = 0;
        $notFound = 0;
        $errors = 0;

        /** @var User $user */
        foreach($users as $user) {
            $photo = $user->getPhoto();
            if ($photo && preg_match('/^uploads\/gallery\//i', $photo->getPath())) {
                if (file_get_contents($photo->getFullPath())) {
                    $photos = $photoManager->getAll($user->getId());
                    /** @var Photo $galleryPhoto */
                    foreach ($photos as $galleryPhoto) {
                        if ($galleryPhoto->getIsProfilePhoto()) {
                            $fixed++;
                            $output->writeln('Set profile photo ' . $galleryPhoto->getPath());
                            $photoManager->setAsProfilePhoto($galleryPhoto, $user);
                        }
                    }
                } else {
                    $errors++;
                    $output->writeln('ERROR: ' . $photo->getFullPath() . ' not found');
                }
            } else {
                $photos = $photoManager->getAll($user->getId());
                /** @var Photo $photo */
                foreach ($photos as $photo) {
                    if (!file_get_contents($photo->getFullPath()) && !$photo->getIsProfilePhoto()) {
                        $notFound++;
                        $output->writeln('Photo for user ' . $user->getUsername() . ' not found and will be deleted');
                        $photoManager->remove($photo->getId());
                    } else if (!file_get_contents($photo->getFullPath())) {
                        $errors++;
                        $output->writeln('ERROR: Profile photo for user ' . $user->getUsername() . ' not found. SET IT MANUALLY!.');
                    }
                }
            }
        }
        $output->writeln($fixed . ' users fixed');
        $output->writeln($notFound . ' not found gallery images');
        $output->writeln($errors . ' ERRORS');
        $output->writeln('Done.');
    }
}
