<?php

namespace App\Form;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class TripSplitFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalTrip = $options['original_trip'];
        $nextTrip     = $options['next_trip'];

        $startDateConstraints = [new GreaterThanOrEqual(
            value: $originalTrip->getStartDate(),
            message: 'trips.split.date.start_min',
        )];

        $endDateConstraints = [];
        if ($nextTrip !== null && $nextTrip->getStartDate() !== null) {
            $endDateConstraints[] = new LessThanOrEqual(
                value: $nextTrip->getStartDate(),
                message: 'trips.split.date.end_max',
            );
        }

        $builder
            ->add(
                'splitMileage',
                IntegerType::class,
                [
                    'label' => 'trips.split.mileage',
                    'constraints' => [
                        new NotBlank(),
                        new GreaterThan(
                            value: $originalTrip->getStartMileage(),
                            message: 'trips.split.mileage.min',
                        ),
                        new LessThan(
                            value: $originalTrip->getEndMileage(),
                            message: 'trips.split.mileage.max',
                        ),
                    ],
                ]
            )
            ->add(
                'startDate',
                DateType::class,
                [
                    'widget' => 'single_text',
                    'label' => 'date.start',
                    'constraints' => $startDateConstraints,
                ]
            )
            ->add(
                'endDate',
                DateType::class,
                [
                    'widget' => 'single_text',
                    'label' => 'date.end',
                    'constraints' => $endDateConstraints,
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    'choices' => [
                        'vacation' => 'vacation',
                        'transport' => 'transport',
                        'service' => 'service',
                        'other' => 'other'
                    ],
                    'choice_translation_domain' => 'messages',
                    'label' => 'trips.type',
                ]
            )
            ->add(
                'comment',
                TextareaType::class,
                [
                    'label' => 'trips.comment',
                    'required' => false,
                    'empty_data' => '',
                ]
            )
            ->add(
                'users',
                EntityType::class,
                [
                    'class' => User::class,
                    'query_builder' => function (EntityRepository $er) use ($options) {
                        return $er->createQueryBuilder('u')
                            ->join('u.userTypes', 'ut')
                            ->andWhere('ut.car = :car')
                            ->setParameter('car', $options['car'])
                            ->orderBy('u.email', 'ASC');
                    },
                    'multiple' => true,
                    'label' => 'user.users',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'car' => null,
            'original_trip' => null,
            'next_trip' => null,
        ]);
    }
}
