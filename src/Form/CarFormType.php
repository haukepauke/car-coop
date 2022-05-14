<?php

namespace App\Form;

use App\Entity\Car;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CarFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['help' => 'Choose a unique name for the car!'])
            ->add(
                'licensePlate',
                TextType::class,
                [
                    'help' => 'Which license plate does the car have (optional)',
                    'required' => false,
                ]
            )
            ->add('mileage', IntegerType::class, ['help' => 'Distance, this car has done in its live so far (be exact here)'])
            ->add(
                'milageUnit',
                ChoiceType::class,
                [
                    'choices' => [
                        'Kilometers' => 'km',
                        'Miles' => 'mi',
                    ],
                ]
            )
            ->add(
                'make',
                TextType::class,
                [
                    'help' => 'Model name of the car (optional)',
                    'required' => false,
                ]
            )
            ->add(
                'vendor',
                TextType::class,
                [
                    'help' => 'Car vendor (optional)',
                    'required' => false,
                ]
            )
            ->add(
                'picture',
                FileType::class,
                [
                    'label' => 'Profile picture',
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new File(
                            [
                                'maxSize' => '4096k',
                                'mimeTypes' => [
                                    'image/jpeg',
                                    'image/png',
                                    'image/gif',
                                ],
                                'mimeTypesMessage' => 'Please upload a Jpeg, PNG or GIF image',
                            ]
                        ),
                    ],
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Car::class,
        ]);
    }
}
