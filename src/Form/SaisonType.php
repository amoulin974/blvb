<?php

namespace App\Form;

use App\Entity\Saison;
use App\Form\IndisponibiliteType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class SaisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la saison',
                'required' => true
            ])
            ->add('points_victoire_forte', TextType::class, [
                'label' => 'Nombre de point gagné en cas de victoire complète (3-0) ',
                'required' => true
            ])
            ->add('points_victoire_faible', TextType::class, [
                'label' => 'Nombre de point gagné en cas de victoire incomplète (3-2) ',
                'required' => true
            ])
            ->add('points_defaite_forte', TextType::class, [
                'label' => 'Nombre de point gagné en cas de defaite totale (0-3) ',
                'required' => true
            ])
            ->add('points_defaite_faible', TextType::class, [
                'label' => 'Nombre de point gagné en cas de defaite partielle (2-3)',
                'required' => true
            ])
            ->add('points_forfait', TextType::class, [
                'label' => 'Nombre de point gagné en cas de forfait',
                'required' => true
            ])
            ->add('points_nul', TextType::class, [
                'label' => 'Nombre de point gagné en cas de null',
                'required' => true
            ])

            ->add('date_debut', DateType::class)
            ->add('date_fin', DateType::class)
            ->add('save', SubmitType::class)

            // --- GESTION DES INDISPONIBILITÉS ---
        ->add('indisponibilites', CollectionType::class, [
            'entry_type' => IndisponibiliteType::class,
            'entry_options' => ['label' => false],
            'allow_add' => true,    // Autorise l'ajout via JS
            'allow_delete' => true, // Autorise la suppression via JS
            'by_reference' => false, // Important pour que setSaison() soit appelé
            'label' => 'Périodes de fermeture (Vacances, Fériés...)',
            // Attributs pour le JavaScript (voir étape 4)
            'attr' => [
                'data-controller' => 'form-collection',
                'data-form-collection-add-label-value' => 'Ajouter une période',
                'data-form-collection-delete-label-value' => 'Supprimer'
            ]
        ])
        ;

}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Saison::class,
        ]);
    }
}

