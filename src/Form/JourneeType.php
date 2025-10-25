<?php

namespace App\Form;

use App\Entity\Journee;
use App\Entity\Phase;
use App\Entity\Poule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JourneeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero')
            ->add('date_debut', null, [
                'widget' => 'single_text',
            ])
            ->add('date_fin', null, [
                'widget' => 'single_text',
            ])
            ->add('poule', EntityType::class, [
                'class' => Poule::class,
                'choice_label' => function (Poule $poule) {
                    return $poule->getNom() . ' - ' . $poule->getPhase()->getSaison()->getNom().' - '.$poule->getPhase()->getNom();
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Journee::class,
        ]);
    }
}
