<?php

namespace App\Form;

use App\Entity\ParametresSite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Réglages de Volley Stats (D68) : seuil d'échanges détaillés pour les classements des joueurs. */
class VolleyStatsReglagesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('seuilEchangesDetailles', IntegerType::class, [
            'label' => 'Échanges détaillés pour qu\'un set compte (%)',
            'help' => 'Un set ne compte dans les classements des joueurs que si cette part de ses échanges est détaillée (touches notées). 80 % par défaut.',
            'attr' => ['min' => 0, 'max' => 100, 'inputmode' => 'numeric', 'class' => 'input input-bordered w-32'],
            'invalid_message' => 'Indiquez un nombre entier entre 0 et 100.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ParametresSite::class]);
    }
}
