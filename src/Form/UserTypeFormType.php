<?php

namespace App\Form;

use App\Entity\UserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        
        $userType = $builder->getData();
        $car = $userType->getCar();

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
                    'label' => 'price.per_unit',
                    'label_translation_parameters' => ['%unit%' => $car->getMilageUnit()],
                ]
            )
            ->add(
                'admin',
                CheckboxType::class,
                [
                    'help' => 'user.group.help.admin',
                    'label' => 'user.group.admin',
                    'required' => false
                ]
            )
            ->add(
                'occasionalUse',
                CheckboxType::class,
                [
                    'help' => 'user.group.help.occasional_use',
                    'label' => 'user.group.occasional_use',
                    'required' => false
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
 