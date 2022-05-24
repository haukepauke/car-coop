<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFormType extends AbstractType
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
                'amount',
                MoneyType::class,
                [
                    'label' => 'amount',
                ]
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
                    'label' => 'payments.type',
                ]
            )
            ->add(
                'fromUser',
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
                    'label' => 'from',
                ]
            )
            ->add(
                'toUser',
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
                    'label' => 'to',
                ]
            )
            ->add('comment')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Payment::class,
            'car' => null,
        ]);
    }
}
