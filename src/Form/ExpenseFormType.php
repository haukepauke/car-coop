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
        $builder
            ->add(
                'date',
                null,
                [
                    'widget' => 'single_text',
                    'label' => 'date.date',
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'expenses.form.fuel' => 'fuel',
                        'expenses.form.maut' => 'maut',
                        'expenses.form.service' => 'service',
                        'expenses.form.other' => 'other',
                    ],
                    'label' => 'expenses.type',
                ]
            )
            ->add(
                'name',
                null,
                [
                    'label' => 'expenses.name',
                ]
            )
            ->add(
                'comment',
                null,
                [
                    'label' => 'comment',
                ]
            )

            ->add(
                'amount',
                MoneyType::class,
                [
                    'label' => 'amount',
                ]
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
