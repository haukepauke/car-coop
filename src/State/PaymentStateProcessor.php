<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Service\PaymentService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof Payment) {
            return $data;
        }

        if ($data->getCar() !== null) {
            foreach ([$data->getFromUser(), $data->getToUser()] as $paymentUser) {
                if ($paymentUser !== null && !$data->getCar()->hasUser($paymentUser)) {
                    throw new AccessDeniedHttpException('Payment users must belong to the selected car.');
                }
            }
        }

        if ($operation instanceof Post) {
            $this->paymentService->createPayment($data);
        } else {
            $this->paymentService->updatePayment($data);
        }

        return $data;
    }
}
