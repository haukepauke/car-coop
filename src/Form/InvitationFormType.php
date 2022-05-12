<?php

namespace App\Form;

use App\Entity\Invitation;
use App\Entity\UserType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvitationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email')
            ->add(
                'userType',
                EntityType::class,
                [
                    'class' => UserType::class,
                    'query_builder' => function (EntityRepository $er) use ($options) {
                        return $er->createQueryBuilder('ut')
                            ->andWhere('ut.car = :car')
                            ->setParameter('car', $options['car'])
                            ->orderBy('ut.id', 'ASC')
                            ->setMaxResults(10)
                            ;
                    },
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invitation::class,
            'car' => null,
        ]);
    }
}
