<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\AnalyzeExamInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AnalyzeExamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workbook', FileType::class, [
                'label' => 'form.workbook',
                'attr' => ['accept' => '.xlsx,.xls'],
            ])
            ->add('caesura', CaesuraType::class, [
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AnalyzeExamInput::class]);
    }
}
