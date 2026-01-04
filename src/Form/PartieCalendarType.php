<?php
// src/Form/PartieCalendarType.php
namespace App\Form;

use App\Entity\Partie;
use App\Entity\Journee;
use App\Entity\Lieu;
use App\Entity\Equipe;
use App\Entity\Poule;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\PreSetDataEvent;
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
        $poule = $options['poule'];
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($poule) {
            $partie = $event->getData();
            $form = $event->getForm();

            if (!$partie) {
                return;
            }

            // Calcul du texte pour le placeholder (Equipe reçoit)
            $placeholderRecoit = 'Choisir une équipe...';
            if (!$partie->getIdEquipeRecoit() && $partie->getParentMatch1()) {
                $parent = $partie->getParentMatch1();
                $placeholderRecoit = 'Vainqueur ' . ($parent->getNom() ?: 'Match #' . $parent->getId());
            }

            // Calcul du texte pour le placeholder (Equipe déplace)
            $placeholderDeplace = 'Choisir une équipe...';
            if (!$partie->getIdEquipeDeplace() && $partie->getParentMatch2()) {
                $parent = $partie->getParentMatch2();
                $placeholderDeplace = 'Vainqueur ' . ($parent->getNom() ?: 'Match #' . $parent->getId());
            }
            $form->add('id_equipe_recoit', EntityType::class, [
                'class' => Equipe::class,
                'choice_label' => 'nom',
                'label' => 'Équipe qui reçoit',
                'required' => false,
                'placeholder' => $placeholderRecoit,
                'query_builder' => function (EntityRepository $er) use ($poule) {
                    return $er->createQueryBuilder('e')
                        ->join('e.Poules', 'p')
                        ->where('p = :poule')
                        ->setParameter('poule', $poule)
                        ->orderBy('e.nom', 'ASC');
                },
            ]);
            $form->add('id_equipe_deplace', EntityType::class, [
                    'class' => Equipe::class,
                    'choice_label' => 'nom',
                    'label' => 'Équipe qui se déplace',
                    'required' => false,
                    'placeholder' => $placeholderDeplace,
                    'query_builder' => function (EntityRepository $er) use ($poule) {
                        return $er->createQueryBuilder('e')
                            ->join('e.Poules', 'p')
                            ->where('p = :poule')
                            ->setParameter('poule', $poule)
                            ->orderBy('e.nom', 'ASC');
                    },
                ]);
        });
        /** @var Poule $poule */

        $builder
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date du match'
            ])

// Liste des journées filtrées par la poule
            ->add('journee', EntityType::class, [
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

// On ne laisse jamais changer la poule → cachée car imposée par le contexte
            ->add('poule', EntityType::class, [
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
