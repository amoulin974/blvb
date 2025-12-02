<?php
// src/Form/PartieCalendarType.php
namespace App\Form;

use App\Entity\Partie;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Equipe;
use App\Entity\Poule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartieCalendarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Poule $poule */
        $poule = $options['poule'];
        $builder
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date du match'
            ])

// ✅ Liste des journées filtrées par la poule
            ->add('id_journee', EntityType::class, [
                'class' => Journee::class,
                'choice_label' => fn(Journee $j) => 'Journée n°' . $j->getNumero(),
                'label' => 'Journée',

                'query_builder' => function (EntityRepository $er) use ($poule) {
                    return $er->createQueryBuilder('j')
                        ->where('j.poule = :poule')
                        ->setParameter('poule', $poule)
                        ->orderBy('j.numero', 'ASC');
                },
            ])

// ✅ On ne laisse jamais changer la poule → cachée car imposée par le contexte
            ->add('Poule', EntityType::class, [
                'class' => Poule::class,
                'choice_label' => fn(Poule $p) => $p->getNom(),
                'data' => $poule,
                'attr' => ['hidden' => true],
                'label' => false,
            ])
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
                'label' => 'Lieu du match'
            ])
            ->add('id_equipe_recoit', EntityType::class, [
                'class' => Equipe::class,
                'choice_label' => 'nom',
                'label' => 'Équipe qui reçoit',
                'query_builder' => function (EntityRepository $er) use ($poule) {
                    return $er->createQueryBuilder('e')
                        ->join('e.Poules', 'p')
                        ->where('p = :poule')
                        ->setParameter('poule', $poule)
                        ->orderBy('e.nom', 'ASC');
                },
            ])
            ->add('id_equipe_deplace', EntityType::class, [
                'class' => Equipe::class,
                'choice_label' => 'nom',
                'label' => 'Équipe qui se déplace',
                'query_builder' => function (EntityRepository $er) use ($poule) {
                    return $er->createQueryBuilder('e')
                        ->join('e.Poules', 'p')
                        ->where('p = :poule')
                        ->setParameter('poule', $poule)
                        ->orderBy('e.nom', 'ASC');
                },
            ])
            ->add('nb_set_gagnant_reception')
            ->add('nb_set_gagnant_deplacement');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partie::class,
            'poule' => null, // ✅ obligation pour filtrer
            'journee' => null, // ✅ on déclare l'option
        ]);
    }
}
