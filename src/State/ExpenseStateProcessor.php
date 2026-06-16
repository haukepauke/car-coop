<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Expense;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ExpenseStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $inner,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Expense) {
            if ($data->getCar() !== null && $data->getUser() !== null && !$data->getCar()->hasUser($data->getUser())) {
                throw new AccessDeniedHttpException('Expense user must belong to the selected car.');
            }

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
