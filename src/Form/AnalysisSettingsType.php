<?php

declare(strict_types=1);

namespace App\Form;

use App\Application\CaesuraNotation;
use App\Form\DataTransformer\CaesuraDataTransformer;
use App\Form\DataTransformer\ExamDataTransformer;
use App\Form\Model\AnalysisSettingsInput;
use App\Infrastructure\Web\ExamDataCodec;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AnalysisSettingsType extends AbstractType
{
    public function __construct(
        private readonly ExamDataCodec $codec,
        private readonly CaesuraNotation $notation,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fileName', HiddenType::class)
            ->add('exam', HiddenType::class)
            ->add('baselineCaesura', HiddenType::class)
            ->add('caesura', CaesuraType::class, ['label' => false])
            ->add('adjustments', CollectionType::class, [
                'entry_type' => ChoiceType::class,
                'entry_options' => [
                    'choices' => [
                        'form.adjustment.keep' => 'keep',
                        'form.adjustment.remove' => 'remove',
                        'form.adjustment.full_marks' => 'full_marks',
                    ],
                    'label' => false,
                ],
                'allow_add' => true,
            ])
            ->add('veryDifficultBelow', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => 0.05, 'max' => 0.95, 'step' => 0.05],
            ])
            ->add('veryEasyAbove', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => 0.05, 'max' => 0.95, 'step' => 0.05],
            ])
            ->add('weakDiscriminationBelow', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'attr' => ['min' => 0.05, 'max' => 0.95, 'step' => 0.05],
            ]);

        $builder->get('exam')->addModelTransformer(new ExamDataTransformer($this->codec));
        $builder->get('baselineCaesura')->addModelTransformer(new CaesuraDataTransformer($this->notation));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AnalysisSettingsInput::class]);
    }
}
