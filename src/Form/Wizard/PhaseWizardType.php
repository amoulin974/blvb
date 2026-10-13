<?php

namespace App\Form\Wizard;

use App\Entity\Phase;
use App\Enum\PhaseType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une phase dans l'étape 3 du wizard : nom, type (seuls les formats disponibles, D29), dates, poules, code.
 * Les poules sont ajoutées, retirées et la phase déplacée par des boutons traités par le contrôleur.
 */
class PhaseWizardType extends AbstractType
{
    public const LIBELLES = [
        'CHAMPIONNAT' => ['Championnat', 'Toutes les équipes de la poule se rencontrent.'],
        'FINALE' => ['Tableau à élimination', 'Le perdant est éliminé.'],
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la phase',
                'empty_data' => '', // champ vidé : message d'erreur, pas d'erreur 500 (setNom(string))
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le nom de la phase.')],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type de phase',
                'class' => PhaseType::class,
                'expanded' => true,
                'choice_label' => fn (PhaseType $type) => self::LIBELLES[$type->name][0],
            ])
            ->add('datedebut', DateType::class, ['label' => 'Du', 'widget' => 'single_text', 'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Indiquez la date de début de la phase.')],
                // Date vidée : message d'erreur ; le setter n'accepte pas null
                'setter' => function (Phase $phase, ?\DateTimeImmutable $date): void { if ($date) { $phase->setDatedebut($date); } }])
            ->add('datefin', DateType::class, ['label' => 'Au', 'widget' => 'single_text', 'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Indiquez la date de fin de la phase.')],
                'setter' => function (Phase $phase, ?\DateTimeImmutable $date): void { if ($date) { $phase->setDatefin($date); } }])
            ->add('code', TextType::class, [
                'label' => 'Code de la phase',
                'required' => false,
                'constraints' => [new Assert\Regex(pattern: '/^[A-Za-z0-9_]{1,20}$/', message: 'Lettres, chiffres ou « _ », sans espace.')],
            ])
            ->add('poules', CollectionType::class, [
                'entry_type' => PouleWizardType::class,
                'entry_options' => ['label' => false],
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Phase::class]);
    }
}
