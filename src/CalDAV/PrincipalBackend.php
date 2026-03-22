<?php

namespace App\CalDAV;

use App\Repository\UserRepository;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class PrincipalBackend extends AbstractBackend
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function getPrincipalsByPrefix($prefixPath)
    {
        return [];
    }

    public function getPrincipalByPath($path)
    {
        if (!str_starts_with($path, 'principals/')) {
            return false;
        }

        $email = substr($path, strlen('principals/'));
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return false;
        }

        return [
            'uri'                                   => 'principals/' . $user->getEmail(),
            '{DAV:}displayname'                     => $user->getName(),
            '{http://sabredav.org/ns}email-address' => $user->getEmail(),
        ];
    }

    public function updatePrincipal($path, PropPatch $propPatch)
    {
        return 0;
    }

    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof')
    {
        return [];
    }

    public function findByUri($uri, $principalPrefix)
    {
        return null;
    }

    public function getGroupMemberSet($principal)
    {
        return [];
    }

    public function getGroupMembership($principal)
    {
        return [];
    }

    public function setGroupMemberSet($principal, array $members)
    {
    }
}
