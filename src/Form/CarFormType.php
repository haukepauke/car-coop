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
            ->add(
                'name',
                TextType::class,
                [
                    'help' => 'car.form.help.name',
                    'label' => 'car.form.name',
                ]
            )
            ->add(
                'licensePlate',
                TextType::class,
                [
                    'help' => 'car.form.help.licenseplate',
                    'required' => false,
                    'label' => 'car.form.licenseplate',
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
                    'label' => 'car.form.mileageunit',
                ]
            )
            ->add(
                'make',
                TextType::class,
                [
                    'help' => 'car.form.help.make',
                    'required' => false,
                    'label' => 'car.form.make',
                ]
            )
            ->add(
                'vendor',
                TextType::class,
                [
                    'help' => 'car.form.help.vendor',
                    'required' => false,
                    'label' => 'car.form.vendor',
                ]
            )
            ->add(
                'picture',
                FileType::class,
                [
                    'label' => 'car.form.profile.picture',
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
                                'mimeTypesMessage' => 'car.form.help.profile.picture',
                            ]
                        ),
                    ],
                    'attr' => [
                        'class' => 'form-control',
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
