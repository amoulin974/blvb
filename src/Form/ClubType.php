<?php

namespace App\Form;

use App\Entity\Club;
use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\User;
use App\Repository\EquipeRepository;
use App\Repository\LieuRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClubType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $multiple = static fn (string $placeholder) => [
            'class' => 'w-full',
            'data-controller' => 'tom-select',
            'data-tom-select-multiple-value' => 'true',
            'data-tom-select-placeholder-value' => $placeholder,
        ];

        $builder
            ->add('nom', null, ['label' => 'Nom du club'])
            ->add('nomCourt', null, [
                'label' => 'Nom court',
                'help' => 'Affiché entre parenthèses après le nom des équipes du club, ex. EROAK (AOVB).',
            ])
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
            ->add('responsables', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsables du club',
                'help' => 'Ils peuvent saisir le score des matchs reçus par les équipes du club. Un responsable nouvellement désigné doit se reconnecter.',
                'choice_label' => static fn (User $u) => trim($u->getPrenom().' '.$u->getNom()).' ('.$u->getEmail().')',
                'query_builder' => static fn (UserRepository $r) => $r->createQueryBuilder('u')->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC'),
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => $multiple('Choisissez les comptes…'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}
