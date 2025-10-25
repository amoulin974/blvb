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

class PartieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', null, [
                'widget' => 'single_text',
            ])
            ->add('nb_set_gagnant_reception')
            ->add('nb_set_gagnant_deplacement')
            ->add('id_journee', EntityType::class, [
                'class' => Journee::class,
                'choice_label' => 'id',
            ])
            ->add('Poule', EntityType::class, [
                'class' => Poule::class,
                'choice_label' => 'id',
            ])
            ->add('id_lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'id',
            ])
            ->add('id_equipe_recoit', EntityType::class, [
                'class' => equipe::class,
                'choice_label' => 'id',
            ])
            ->add('id_equipe_deplace', EntityType::class, [
                'class' => equipe::class,
                'choice_label' => 'id',
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
