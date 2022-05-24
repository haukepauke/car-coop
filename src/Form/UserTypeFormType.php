<?php

namespace App\Form;

use App\Entity\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'name',
                TextType::class,
                [
                    'help' => 'user.group.help.name',
                    'label' => 'user.group.name',
                ]
            )
            ->add(
                'pricePerUnit',
                MoneyType::class,
                [
                    'help' => 'price.help.per.unit',
                    'label' => 'price.per'.' '.'price.unit',
                ]
            )
            // TODO: query for users of current car only
            ->add(
                'users',
                null,
                [
                    'label' => 'user.users',
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserType::class,
        ]);
    }
}
