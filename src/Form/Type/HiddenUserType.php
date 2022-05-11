<?php

namespace App\Form\Type;

use App\Entity\User;
use App\Form\DataTransformer\EmailToUserTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HiddenUserType extends AbstractType
{
    private $transformer;

    public function __construct(EmailToUserTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function getParent()
    {
        return HiddenType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $builder->add('user', HiddenType::class);

        $builder->addModelTransformer($this->transformer);
    }

    // public function configureOptions(OptionsResolver $resolver)
    // {
    //     $resolver->setDefaults(['data_class' => User::class]);
    // }
}
