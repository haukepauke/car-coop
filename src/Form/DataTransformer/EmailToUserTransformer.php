<?php

namespace App\Form\DataTransformer;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class EmailToUserTransformer implements DataTransformerInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function transform($user)
    {
        if (null === $user) {
            return '';
        }

        if (!$user instanceof User) {
            throw new LogicException('This Field type can only be used with user objects');
        }

        return $user->getEmail();
    }

    public function reverseTransform(mixed $email)
    {
        dd('reverseTransform', $email);
        if (!$email) {
            return null;
        }

        $user = $this->em->getRepository(User::class)->find($email);

        if (null === $user) {
            throw new TransformationFailedException(sprintf('An user with id "%s" does not exist!', $email));
        }

        return $user;
    }
}
