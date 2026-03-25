<?php

namespace App\Form;

use App\Entity\UserType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class InvitationEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('userType', EntityType::class, [
                'class' => UserType::class,
                'query_builder' => function (EntityRepository $er) use ($options) {
                    return $er->createQueryBuilder('ut')
                        ->andWhere('ut.car = :car')
                        ->andWhere('ut.active = true')
                        ->setParameter('car', $options['car'])
                        ->orderBy('ut.id', 'ASC')
                        ->setMaxResults(10);
                },
                'label' => 'user.group.group',
            ])
            ->add('locale', ChoiceType::class, [
                'label'   => 'invitation.email.language',
                'choices' => ['English' => 'en', 'Deutsch' => 'de', 'Nederlands' => 'nl', 'Français' => 'fr', 'Español' => 'es', 'Polski' => 'pl'],
                'data'    => $options['default_locale'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => null,
            'car'            => null,
            'default_locale' => 'en',
        ]);
    }
}
