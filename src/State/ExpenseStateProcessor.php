<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Expense;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class ExpenseStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $inner,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Expense) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setEditor($user);
                if ($data->getUser() === null) {
                    $data->setUser($user);
                }
            }
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
