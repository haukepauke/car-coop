<?php

namespace App\Form;

use App\Entity\Expense;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExpenseFormType extends AbstractType
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
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'Fuel' => 'fuel',
                        'Maut' => 'maut',
                        'Workshop/Service' => 'service',
                        'Other' => 'other',
                    ],
                ]
            )
            ->add(
                'name'
            )
            ->add('comment')

            ->add(
                'amount',
                MoneyType::class
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Expense::class,
        ]);
    }
}
