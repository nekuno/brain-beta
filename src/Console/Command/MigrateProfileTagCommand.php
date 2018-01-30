<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Model\Entity\DataStatus;
use Model\LanguageText\LanguageTextManager;
use Model\Neo4j\Neo4jException;
use Model\User\ProfileTagModel;
use Model\User\Token\TokenStatus\TokenStatusManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MigrateProfileTagCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('migrate:profile-tag')
            ->setDescription('Migrate profile tag texts to their own nodes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ProfileTagModel $profileTagManager */
        $profileTagManager = $this->app['users.profile.tag.model'];
        /** @var LanguageTextManager $languageTextManager */
        $languageTextManager = $this->app['users.languageText.manager'];
        $tags = $profileTagManager->findAllOld();

        $output->writeln(count($tags) . ' tags with different locales found');

        $tags = $this->selectProbableLocale($tags);

        $output->writeln(count($tags) . ' different tags found');

        foreach ($tags as $tag)
        {
            $output->writeln('Migrating tag' . $tag['id']);
            $languageTextManager->merge($tag['id'], $tag['locale'], $tag['name']);
            $profileTagManager->deleteName($tag['id']);
        }

        $output->writeln('Done');
    }

    protected function selectProbableLocale(array $tags)
    {
        $filteredTags = array();
        foreach ($tags as $tag)
        {
            $id = $tag['id'];
            if (!isset($filteredTags[$id]) || $filteredTags[$id]['amount'] < $tag['amount']){
                $filteredTags[$id] = $tag;
            }
        }

        return $filteredTags;
    }
}
