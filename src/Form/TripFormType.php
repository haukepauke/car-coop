<?php

namespace App\Form;

use App\Entity\Trip;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TripFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $trip = $options['data'] ?? null;
        // $isEdit = $trip && $trip->getId();

        $builder
            ->add(
                'startMileage',
                null,
                ['disabled' => true]
            )
            ->add('endMileage')
            ->add(
                'startDate',
                null,
                ['widget' => 'single_text']
            )
            ->add(
                'endDate',
                null,
                ['widget' => 'single_text']
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'Vacation' => 'vacation',
                        'Transport' => 'transport',
                        'Workshop/Service' => 'service',
                    ],
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
        ]);
    }
}
