<?php

namespace App\Form;

use App\Entity\CarHandbook;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CarHandbookFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'handbook.form.content',
                'attr' => [
                    'rows' => 20,
                    'class' => 'font-monospace',
                    'spellcheck' => 'false',
                ],
            ])
            ->add('photos', FileType::class, [
                'label' => 'handbook.form.photos',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'accept' => '.jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif',
                ],
                'help' => 'handbook.photo_hint',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CarHandbook::class,
        ]);
    }
}
