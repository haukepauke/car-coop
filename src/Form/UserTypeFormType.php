<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\UserType;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
                    'label' => 'price.per'.' '.'price.unit',
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
            //TODO Prohibit deletion of users from group, use move form instead
            //Use Form event listener to create an error message when a user tries to remove a user from group 
            //https://symfonycasts.com/screencast/symfony-forms/dynamic-form-events
            ->add(
                'users',
                EntityType::class,
                [
                    'class' => User::class,
                    'choices' => $car->getUsers(),
                    'label' => 'user.users',
                    'multiple' => true,
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
 