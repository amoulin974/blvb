<?php

namespace App\Form;

use App\Entity\Journee;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Entity\Phase;
use App\Repository\PhaseRepository;
use App\Entity\Poule;
use App\Entity\Equipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PouleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('niveau')
            ->add('phase', EntityType::class, [
                'class' => phase::class,
                'choice_label' => function (Phase $phase) {
                    return $phase->getNom() . ' - ' . $phase->getSaison()->getNom();
                },
                'query_builder' => function (PhaseRepository $repo) {
                    return $repo->createQueryBuilder('p')
                        ->join('p.saison', 's')
                        ->orderBy('s.id', 'DESC')
                        ->addOrderBy('p.id', 'ASC');
                },
            ])
            ->add('equipes', EntityType::class, [
                'class' => Equipe::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,// select multiple natif
                'required' => false,
                'by_reference' => false,
                'attr' => [
                    'class' => 'select select-bordered w-full',
                    'data-tom-select-multiple-value' => 'true',
                    'data-tom-select-placeholder-value' => 'Choisissez une equipe...'
                ],

            ])
            ->add('journees', CollectionType::class, [
                'entry_type' => JourneeType::class,
                'allow_add' => true,      // autorise à ajouter des journées
                'allow_delete' => true,   // autorise à supprimer
                'by_reference' => false,  // important pour ManyToOne
                'prototype' => true,      // pour JS si ajout dynamique
            ])
        ;


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Poule::class,
        ]);
    }
}
