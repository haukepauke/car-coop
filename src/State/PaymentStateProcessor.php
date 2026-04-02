<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Payment;
use App\Service\PaymentService;

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

        if ($operation instanceof Post) {
            $this->paymentService->createPayment($data);
        } else {
            $this->paymentService->updatePayment($data);
        }

        return $data;
    }
}
