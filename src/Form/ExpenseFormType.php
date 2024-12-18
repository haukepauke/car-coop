<?php

namespace App\Form;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            'data_class' => Expense::class,
            'car' => null,
        ]);
    }
}
