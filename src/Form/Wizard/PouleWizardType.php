<?php

namespace App\Form\Wizard;

use App\Entity\Poule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/** Une poule (ou un tableau) dans l'étape 3 du wizard. */
class PouleWizardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le nom de la poule.')],
            ])
            ->add('niveau', IntegerType::class, [
                'label' => 'Niveau (1 = le plus fort)',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le niveau de la poule.'), new Assert\Positive(message: 'Le niveau est un nombre à partir de 1.')],
            ])
            ->add('nbEquipesPrevu', IntegerType::class, [
                'label' => 'Équipes prévues',
                'required' => false,
                'constraints' => [new Assert\Range(min: 2, max: 40, notInRangeMessage: 'Entre {{ min }} et {{ max }} équipes.')],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'required' => false,
                'constraints' => [new Assert\Regex(pattern: '/^[A-Za-z0-9_]{1,20}$/', message: 'Lettres, chiffres ou « _ », sans espace.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Poule::class]);
    }
}
