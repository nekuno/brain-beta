<?php

namespace Model\User\SocialNetwork;

use Psr\Log\LoggerInterface;

class LinkedinSocialNetworkModel extends SocialNetworkModel
{
    public function set($id, $profileUrl, LoggerInterface $logger = null)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(u:User)-[:HAS_SOCIAL_NETWORK {url: { profileUrl }}]->(:LinkedinSocialNetwork)')
            ->setParameter('profileUrl', $profileUrl)
            ->where('u.qnoow_id = { id }')
            ->setParameter('id', (int)$id)
            ->with('u');

        $qb->returns('u');

        $result = $qb->getQuery()->getResultSet();

        return count($result) == 1 ? true : false;
    }

    public function getData($profileUrl, LoggerInterface $logger = null)
    {
        return $this->parser->parse($profileUrl, $logger);
    }

    public function migrateAllSkillsOld()
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(skill:Skill)<-[rel:HAS_SKILL]-(:User)-[:HAS_PROFILE]->(profile:Profile)')
            ->where('NOT (skill:ProfileTag)');

        $qb->set('(skill:ProfileTag)')
            ->remove('(skill:Skill)')
            ->merge('(skill)<-[:TAGGED]-(profile)')
            ->delete('rel');

        $qb->getQuery()->getResultSet();
    }

    public function migrateAllLanguagesOld()
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(language:Language)<-[rel:SPEAKS_LANGUAGE]-(:User)-[:HAS_PROFILE]->(profile:Profile)')
            ->where('NOT (language:ProfileTag)');

        $qb->set('(language:ProfileTag)')
            ->remove('(language:Language)')
            ->merge('(language)<-[:TAGGED]-(profile)')
            ->delete('rel');

        $qb->getQuery()->getResultSet();
    }

    public function deleteAllSkillsAndLanguagesToGhostUser()
    {
        $qb = $this->gm->createQueryBuilder();

        $qb->match('(u:GhostUser)')
            ->optionalMatch('(u)-[rel:HAS_SKILL]->()')
            ->optionalMatch('(u)-[rel2:SPEAKS_LANGUAGE]->()')
            ->delete('rel', 'rel2');

        $qb->getQuery()->getResultSet();
    }
}
