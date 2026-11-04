<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\AnalyzeExamInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AnalyzeExamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workbook', FileType::class, [
                'label' => 'Exam results workbook',
                'attr' => ['accept' => '.xlsx,.xls'],
            ])
            ->add('caesuraPoints', CollectionType::class, [
                'label' => 'Caesura',
                'entry_type' => CaesuraPointType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype_name' => '__point__',
                'error_bubbling' => false,
            ])
            ->add('passPercentage', NumberType::class, [
                'label' => 'Pass from',
                'html5' => true,
                'invalid_message' => 'Enter a number.',
                'attr' => ['min' => 0, 'max' => 100, 'step' => 'any', 'inputmode' => 'decimal'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AnalyzeExamInput::class]);
    }
}
