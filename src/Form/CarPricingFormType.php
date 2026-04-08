<?php

namespace App\Form;

use App\Entity\Car;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;

class CarPricingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fuelPrice', NumberType::class, [
                'mapped'      => false,
                'required'    => true,
                'scale'       => 3,
                'label'       => 'car.pricing.fuel_price.label',
                'help'        => 'car.pricing.fuel_price.help',
                'constraints' => [new Positive()],
            ])
            ->add('fuelConsumption100', NumberType::class, [
                'required'    => true,
                'scale'       => 1,
                'label'       => 'car.form.fuelconsumption100',
                'help'        => 'car.form.help.fuelconsumption100',
                'constraints' => [new Positive()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Car::class]);
    }
}
