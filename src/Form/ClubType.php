<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\User;
use App\Form\Front\ClubFicheType;
use App\Repository\EquipeRepository;
use App\Repository\LieuRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Club dans l'admin : la fiche (ClubFicheType) plus les équipes, les gymnases et les responsables. */
class ClubType extends AbstractType
{
    public function getParent(): string
    {
        return ClubFicheType::class;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $multiple = static fn (string $placeholder) => [
            'class' => 'w-full',
            'data-controller' => 'tom-select',
            'data-tom-select-multiple-value' => 'true',
            'data-tom-select-placeholder-value' => $placeholder,
        ];

        $builder
            ->add('equipes', EntityType::class, [
                'class' => Equipe::class,
                'label' => 'Équipes du club',
                'help' => 'Une équipe appartient à un seul club : la choisir ici la retire de son club actuel.',
                // Nom affiché, avec le nom court du club actuel : repère une équipe déjà rattachée ailleurs
                'choice_label' => 'nom',
                'query_builder' => static fn (EquipeRepository $r) => $r->createQueryBuilder('e')->leftJoin('e.club', 'c')->addSelect('c')->orderBy('e.nom', 'ASC'),
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => $multiple('Choisissez les équipes…'),
            ])
            ->add('lieux', EntityType::class, [
                'class' => Lieu::class,
                'label' => 'Gymnases du club',
                'help' => 'Un gymnase peut être partagé par plusieurs clubs.',
                'choice_label' => 'nom',
                'query_builder' => static fn (LieuRepository $r) => $r->createQueryBuilder('l')->orderBy('l.nom', 'ASC'),
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => $multiple('Choisissez les gymnases…'),
            ])
        ;

        // Responsables : désignés pour la saison en cours (option « saison »), comme les capitaines.
        // Champ non lié à l'entité : le contrôleur applique la liste avec Club::setResponsables().
        $saison = $options['saison'];
        if ($saison !== null) {
            $builder->add('responsables', EntityType::class, [
                'class' => User::class,
                'mapped' => false,
                'data' => $builder->getData()?->getResponsables($saison) ?? [],
                'label' => 'Responsables du club pour la '.mb_strtolower((string) $saison->getNom()),
                'help' => 'Pendant cette saison, ils saisissent le score de tous les matchs des équipes du club et modifient la fiche du club. Les responsables des saisons précédentes sont conservés. Un responsable nouvellement désigné doit se reconnecter.',
                'choice_label' => static fn (User $u) => trim($u->getPrenom().' '.$u->getNom()).' ('.$u->getEmail().')',
                'query_builder' => static fn (UserRepository $r) => $r->createQueryBuilder('u')->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC'),
                'multiple' => true,
                'required' => false,
                'attr' => $multiple('Choisissez les comptes…'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
            'saison' => null,
        ]);
        $resolver->setAllowedTypes('saison', ['null', \App\Entity\Saison::class]);
    }
}
