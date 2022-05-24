<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'startDate',
                null,
                [
                    'widget' => 'single_text',
                    'label' => 'booking.form.startdate',
                ]
            )
            ->add(
                'endDate',
                null,
                [
                    'widget' => 'single_text',
                    'label' => 'booking.form.enddate',
                ]
            )
            ->add(
                'title',
                TextType::class,
                [
                    'required' => false,
                    'label' => 'booking.form.title',
                ]
            )
            ->add(
                'status',
                ChoiceType::class,
                [
                    'choices' => [
                        'Fixed!' => 'fixed',
                        'Maybe?' => 'maybe',
                    ],
                    'label' => 'booking.form.status',
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
