<?php

namespace App\Form\Front;

use App\Entity\Joueur;
use App\Repository\JoueurRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Formulaire d'ajout d'une fiche Joueur existante à la composition d'une équipe pour une saison.
class AjouterJoueurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $joueursExclusIds = $options['joueurs_exclus_ids'];

        $builder->add('joueur', EntityType::class, [
            'class' => Joueur::class,
            'label' => 'Joueur à ajouter',
            'placeholder' => 'Choisissez un joueur',
            'attr' => ['data-controller' => 'tom-select'],
            'query_builder' => function (JoueurRepository $joueurRepository) use ($joueursExclusIds): QueryBuilder {
                $qb = $joueurRepository->createQueryBuilder('j')->orderBy('j.nom', 'ASC')->addOrderBy('j.prenom', 'ASC');

                if ($joueursExclusIds !== []) {
                    $qb->andWhere('j.id NOT IN (:exclus)')->setParameter('exclus', $joueursExclusIds);
                }

                return $qb;
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'joueurs_exclus_ids' => [],
        ]);
    }
}
