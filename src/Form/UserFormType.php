<?php

namespace App\Form;

use App\Entity\User;
use App\Service\FileUploaderService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'email',
                null,
                [
                    'label' => 'email',
                ]
            )
            ->add(
                'name',
                null,
                [
                    'label' => 'user.name',
                ]
            )
            ->add(
                'locale',
                ChoiceType::class,
                [
                    'choices' => [
                        'English' => 'en',
                        'Deutsch' => 'de',
                        'Nederlands' => 'nl',
                        'Français' => 'fr',
                        'Español' => 'es',
                        'Polski' => 'pl',
                    ],
                    'label' => 'user.locale',
                ]
            )
            ->add(
                'themePreference',
                ChoiceType::class,
                [
                    'choices' => [
                        'user.theme.light' => 'light',
                        'user.theme.dark' => 'dark',
                        'user.theme.classic' => 'classic',
                    ],
                    'label' => 'user.theme.label',
                ]
            )
            ->add(
                'showWelcomeTour',
                CheckboxType::class,
                [
                    'label' => 'user.tour.show',
                    'required' => false,
                ]
            )
            ->add(
                'color',
                ColorType::class,
                [
                    'label' => 'user.calendar.color',
                ]
            )
            ->add(
                'picture',
                FileType::class,
                [
                    'label' => 'user.profile.picture',
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Image(
                            [
                                'maxSize' => '4096k',
                                'mimeTypes' => array_keys(FileUploaderService::ALLOWED_RASTER_MIME_TYPES),
                                'mimeTypesMessage' => 'Please upload a Jpeg, PNG or GIF image file',
                            ]
                        ),
                    ],
                    'attr' => [
                        'class' => 'form-control',
                        'accept' => '.jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif',
                    ],
                ]
            )->add(
                'notifiedOnEvents',
                ChoiceType::class,
                [
                    'choices' => [
                        'yes' => true,
                        'no' => false,
                    ],
                    'label' => 'user.events.notify',
                ],
            )->add(
                'notifiedOnOwnEvents',
                ChoiceType::class,
                [
                    'choices' => [
                        'yes' => true,
                        'no' => false,
                    ],
                    'label' => 'user.events.own.notify',
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
