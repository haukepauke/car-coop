<?php

namespace App\Form;

use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
                    'label' => 'trips.comment',
                    'empty_data' => '',
                ]
            )
            ->add(
                'user',
                EntityType::class,
                [
                    'class' => User::class,
                    'query_builder' => function (EntityRepository $er) use ($options) {
                        return $er->createQueryBuilder('u')
                            ->join('u.userTypes', 'ut')
                            ->andWhere('ut.car = :car')
                            ->setParameter('car', $options['car'])
                            ->orderBy('u.email', 'ASC')
                            ;
                    },
                    'label' => 'user.user',
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
            'car' => null,
        ]);
    }
}
