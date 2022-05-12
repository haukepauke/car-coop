<?php

namespace App\Form;

use App\Entity\Payment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $expense = $options['data'] ?? null;
        // $isEdit = $expense && $expense->getId();

        $builder
            ->add(
                'date',
                null,
                ['widget' => 'single_text']
            )
            ->add(
                'amount',
                MoneyType::class
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'Cash' => 'cash',
                        'Paypal' => 'paypal',
                        'Bank transfer' => 'banktransfer',
                        'Other' => 'other',
                    ],
                ]
            )
            ->add(
                'toUser'
            )
            ->add('comment')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
        ]);
    }
}
