<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Manager\PhotoManager;
use Manager\UserManager;
use Model\Photo;
use Model\User;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateProfilePhotoCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('users:migrate-profile-photo')
            ->setDescription('Migrate profile photos to all users if it is not migrated yet.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $usersManager UserManager */
        $usersManager = $this->app['users.manager'];
        /* @var $photoManager PhotoManager */
        $photoManager = $this->app['users.photo.manager'];

        $users = $usersManager->getAll(true);

        /** @var User $user */
        foreach($users as $user) {
            $photos = $photoManager->getAll($user->getId());
            /** @var Photo $photo */
            foreach ($photos as $photo) {
                if ($photo->getIsProfilePhoto()) {
                    continue 2;
                }
            }

            /** @var Photo $lastPhoto */
            $lastPhoto = end($photos); // first uploaded photo

            $username = $user->getUsername();

            if ($lastPhoto) {
                // Set last photo as profile photo (old profile photo will be overwritten)
                $output->writeln($username . ': Set last photo ' . $lastPhoto->getFullPath() . ' as profile photo (old profile photo will be overwritten)');
                $oldPhoto = $user->getPhoto();
                $photoManager->setAsProfilePhoto($lastPhoto, $user, 5, 5, 90, 90);
                if ($oldPhoto) {
                    $oldPhoto->delete();
                }

            } else if ($user->getPhoto()) {
                // Save old profile photo in gallery and save as profile photo
                $output->writeln($username . ': Save old profile photo ' . $user->getPhoto()->getUrl() . ' in gallery and save as profile photo');
                $newPhoto = $photoManager->create($user, @file_get_contents($user->getPhoto()->getUrl()));
                $photoManager->setAsProfilePhoto($newPhoto, $user);

            } else {
                // Save default photo in gallery and save as profile photo
                $defaultPhotoUrl = $this->app['images_web_dir'] . 'bundles/qnoowlanding/images/user-no-img.jpg';
                $output->writeln($username . ': Save default photo ' . $defaultPhotoUrl . ' in gallery and save as profile photo');
                $newPhoto = $photoManager->create($user, @file_get_contents($defaultPhotoUrl));
                $photoManager->setAsProfilePhoto($newPhoto, $user);
            }
        }

        $output->writeln('Done.');
    }
}
