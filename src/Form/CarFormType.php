<?php

namespace App\Form;

use App\Entity\Car;
use App\Service\CurrencyService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CarFormType extends AbstractType
{
    public function __construct(private readonly CurrencyService $currencyService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mileageDisabled  = $options['mileage_disabled'];
        $currencyDisabled = $options['currency_disabled'];

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
            ->add('mileage', IntegerType::class, [
                'label'    => 'car.form.mileage',
                'help'     => $mileageDisabled ? 'car.form.help.mileage_locked' : 'car.form.help.mileage',
                'disabled' => $mileageDisabled,
            ])
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
                'currency',
                ChoiceType::class,
                [
                    'choices' => CurrencyService::getFormChoices(),
                    'label'    => 'car.form.currency',
                    'disabled' => $currencyDisabled,
                    'help'     => $currencyDisabled ? 'car.form.help.currency_locked' : null,
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
                'fuelType',
                ChoiceType::class,
                [
                    'choices' => [
                        'car.form.fueltype.petrol'   => 'petrol',
                        'car.form.fueltype.diesel'   => 'diesel',
                        'car.form.fueltype.electric' => 'electric',
                        'car.form.fueltype.hybrid'   => 'hybrid',
                        'car.form.fueltype.lpg'      => 'lpg',
                        'car.form.fueltype.other'    => 'other',
                    ],
                    'required' => false,
                    'placeholder' => 'car.form.fueltype.placeholder',
                    'label' => 'car.form.fueltype',
                ]
            )
        ;

        if ($options['show_fuel_consumption']) {
            $builder->add(
                'fuelConsumption100',
                NumberType::class,
                [
                    'required' => false,
                    'scale' => 1,
                    'label' => 'car.form.fuelconsumption100',
                    'help' => 'car.form.help.fuelconsumption100',
                ]
            );
        }

        $builder->
add(
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
            'data_class'             => Car::class,
            'mileage_disabled'       => false,
            'currency_disabled'      => false,
            'show_fuel_consumption'  => true,
        ]);
        $resolver->setAllowedTypes('mileage_disabled', 'bool');
        $resolver->setAllowedTypes('currency_disabled', 'bool');
        $resolver->setAllowedTypes('show_fuel_consumption', 'bool');
    }
}
