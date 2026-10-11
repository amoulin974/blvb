<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Lieu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Nom enregistré, sans le nom court du club (ajouté à l'affichage : Equipe::getNom)
            ->add('nomSansClub', null, ['label' => "Nom de l'équipe"])
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
            ])
            // Club : ses responsables peuvent saisir les scores des matchs reçus par l'équipe
            ->add('club', EntityType::class, [
                'class' => Club::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => 'Aucun club',
            ])
            // La composition (joueurs + capitaine) se gère désormais par saison,
            // voir EquipeCompositionController / "Gérer la composition" sur la page de l'équipe.
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipe::class,
        ]);
    }
}
