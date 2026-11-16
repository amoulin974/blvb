<?php

namespace App\Form\Wizard;

use App\Entity\Saison;
use App\Repository\SaisonRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Étape 1 du wizard : copier une saison existante ou partir d'un modèle (docs/wizard-saison/archive/ecrans.md, D10, D29). */
class DepartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('depart', ChoiceType::class, [
                'label' => 'Comment voulez-vous commencer ?',
                'expanded' => true,
                'choices' => ['Copier une saison existante' => 'copie', 'Partir d\'un modèle' => 'modele'],
            ])
            ->add('saison', EntityType::class, [
                'label' => 'Saison à copier',
                'class' => Saison::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => false,
                // Saisons terminées ou en cours, jamais un autre brouillon ; la plus récente d'abord
                'query_builder' => fn (SaisonRepository $r) => $r->createQueryBuilder('s')
                    ->where('s.brouillon = false')->orderBy('s.favori', 'DESC')->addOrderBy('s.date_debut', 'DESC'),
            ])
            ->add('modele', ChoiceType::class, [
                'label' => 'Modèle',
                'choices' => $options['modeles'],
                'required' => false,
                'placeholder' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['modeles' => [], 'data' => ['depart' => 'copie']]);
        $resolver->setAllowedTypes('modeles', 'array');
    }
}
