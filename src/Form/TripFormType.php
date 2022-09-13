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
        $builder
            ->add(
                'startMileage',
                null,
                [
                    'disabled' => true,
                    'label' => 'trips.mileage.start',
                ]
            )
            ->add(
                'endMileage',
                null,
                [
                    'label' => 'trips.mileage.end',
                ]
            )
            ->add(
                'startDate',
                null,
                [
                    'widget' => 'single_text',
                    'label' => 'date.start',
                ]
            )
            ->add(
                'endDate',
                null,
                [
                    'widget' => 'single_text',
                    'label' => 'date.end',
                ]
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
                    'label' => 'trips.type',
                ]
            )->add(
                'comment',
                null,
                [
                    'label' => 'comment',
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
