<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Partie;
use App\Entity\Poule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
class PartieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text', // input type="datetime-local"
                'html5' => true,
                'label' => 'Date et heure du match',
            ])
            ->add('nb_set_gagnant_reception')
            ->add('nb_set_gagnant_deplacement')
            ->add('id_journee', EntityType::class, [
                'class' => Journee::class,
                'choice_label' => function(Journee $journee) {
                    return 'Journée n°' . $journee->getNumero();
                },
                'label' => 'Journée',
            ])
            ->add('Poule', EntityType::class, [
                'class' => Poule::class,
                'choice_label' => function(Poule $poule) {
                    $phase = $poule->getPhase();
                    $saison = $phase ? $phase->getSaison() : null;

                    $saisonNom = $saison ? $saison->getNom() : '';
                    $phaseNom = $phase ? $phase->getNom() : '';
                    $pouleNom = $poule->getNom();

                    return "$saisonNom / $phaseNom / $pouleNom";
                },
            ])
            ->add('id_lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
            ])
            ->add('id_equipe_recoit', EntityType::class, [
                'class' => equipe::class,
                'choice_label' => 'nom',
            ])
            ->add('id_equipe_deplace', EntityType::class, [
                'class' => equipe::class,
                'choice_label' => 'nom',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partie::class,
        ]);
    }
}
