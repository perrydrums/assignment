<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\CaesuraPointInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CaesuraPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('percentage', NumberType::class, [
                'label' => 'form.score_percentage',
                'html5' => true,
                'invalid_message' => 'form.number',
                'attr' => ['min' => 0, 'max' => 100, 'step' => 'any', 'inputmode' => 'decimal'],
            ])
            ->add('grade', NumberType::class, [
                'label' => 'form.grade',
                'html5' => true,
                'invalid_message' => 'form.number',
                'attr' => ['step' => 'any', 'inputmode' => 'decimal'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CaesuraPointInput::class]);
    }
}
