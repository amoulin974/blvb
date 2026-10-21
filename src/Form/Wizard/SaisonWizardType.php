<?php

namespace App\Form\Wizard;

use App\Entity\Saison;
use App\Form\IndisponibiliteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Étape 2 du wizard : nom, dates et périodes de fermeture de la saison. */
class SaisonWizardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la saison',
                'help' => 'Proposé à partir de la date de début. Doit être unique.',
                'empty_data' => '', // champ vidé : message d'erreur, pas d'erreur 500 (setNom(string))
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le nom de la saison.')],
            ])
            ->add('date_debut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Indiquez la date de début.')],
                // Date vidée : message d'erreur ; le setter n'accepte pas null
                'setter' => function (Saison $saison, ?\DateTimeImmutable $date): void { if ($date) { $saison->setDateDebut($date); } },
            ])
            ->add('date_fin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'setter' => function (Saison $saison, ?\DateTimeImmutable $date): void { if ($date) { $saison->setDateFin($date); } },
                'constraints' => [new Assert\NotNull(message: 'Indiquez la date de fin.'), new Assert\Callback(function (?\DateTimeImmutable $fin, ExecutionContextInterface $contexte): void {
                    $debut = $contexte->getRoot()->get('date_debut')->getData();
                    if ($fin !== null && $debut !== null && $fin <= $debut) {
                        $contexte->buildViolation('La date de fin doit être après la date de début.')->addViolation();
                    }
                })],
            ])
            ->add('indisponibilites', CollectionType::class, [
                'label' => 'Périodes de fermeture (vacances, jours fériés)',
                'entry_type' => IndisponibiliteType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'attr' => [
                    'data-controller' => 'form-collection',
                    'data-form-collection-add-label-value' => 'Ajouter une période',
                    'data-form-collection-delete-label-value' => 'Supprimer la période',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Saison::class]);
    }
}
