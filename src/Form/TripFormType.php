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
        $editMode = $options['edit_mode'];

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
                    'disabled' => $editMode,
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
                    'choices' => array_combine(Trip::TYPES, Trip::TYPES),
                    'choice_translation_domain' => 'messages',
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
                'users',
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
                    'multiple' => true,
                    'label' => 'user.users',
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Trip::class,
            'car' => null,
            'edit_mode' => false,
        ]);
    }
}
