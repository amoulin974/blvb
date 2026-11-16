<?php

namespace App\Form;

use App\Entity\FermetureGymnase;
use App\Entity\Lieu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie d'une fermeture de gymnase. Option « lieux » : gymnases proposés (ceux du club) ; sans elle, le gymnase est
 * déjà fixé par la page (admin) et le champ n'est pas affiché.
 */
class FermetureGymnaseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['lieux'] !== null) {
            $builder->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choices' => $options['lieux'],
                'choice_label' => 'nom',
                'label' => 'Gymnase',
                'placeholder' => \count($options['lieux']) > 1 ? 'Choisissez le gymnase' : false,
                'constraints' => [new Assert\NotNull(message: 'Choisissez le gymnase.')],
                'label_attr' => ['class' => 'block mb-1 font-semibold'],
                'attr' => ['class' => 'select select-bordered w-full'],
                'row_attr' => ['class' => 'form-control w-full'],
            ]);
        }

        $builder
            ->add('motif', TextType::class, [
                'label' => 'Motif',
                'help' => 'Visible par tous sur le calendrier par gymnase : restez bref, n\'indiquez ni nom ni coordonnées. Les administrateurs voient qui a saisi la fermeture.',
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indiquez le motif de la fermeture.'),
                    new Assert\Length(max: 255, maxMessage: 'Le motif ne peut pas dépasser {{ limit }} caractères.'),
                    new Assert\Regex(pattern: '/[\p{Cc}\p{Cf}]/u', match: false, message: 'Le motif ne peut pas contenir de caractères de contrôle.'),
                ],
                'label_attr' => ['class' => 'block mb-1 font-semibold'],
                'attr' => ['class' => 'input input-bordered w-full', 'placeholder' => 'Ex. : travaux, compétition de judo', 'maxlength' => 255],
                'row_attr' => ['class' => 'form-control w-full'],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Premier jour de fermeture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Indiquez le premier jour de la fermeture.')],
                'label_attr' => ['class' => 'block mb-1 font-semibold'],
                'attr' => ['class' => 'input input-bordered w-full'],
                'row_attr' => ['class' => 'form-control w-full'],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Dernier jour de fermeture',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new Assert\NotNull(message: 'Indiquez le dernier jour de la fermeture.'), new Assert\Callback(function (?\DateTimeImmutable $fin, ExecutionContextInterface $contexte): void {
                    $debut = $contexte->getObject()?->getParent()?->get('dateDebut')->getData();
                    if ($fin !== null && $debut !== null && $fin < $debut) {
                        $contexte->buildViolation('Le dernier jour ne peut pas précéder le premier.')->addViolation();
                    } elseif ($fin !== null && $fin < new \DateTimeImmutable('today')) {
                        $contexte->buildViolation('Cette période est déjà terminée.')->addViolation();
                    } elseif ($fin !== null && $debut !== null && $fin > $debut->modify('+2 years')) {
                        $contexte->buildViolation('Une fermeture ne peut pas dépasser 2 ans : vérifiez les dates.')->addViolation();
                    } elseif ($fin !== null && $fin > new \DateTimeImmutable('today +5 years')) {
                        $contexte->buildViolation('Cette date est trop lointaine : vérifiez l\'année.')->addViolation();
                    }
                })],
                'label_attr' => ['class' => 'block mb-1 font-semibold'],
                'attr' => ['class' => 'input input-bordered w-full'],
                'row_attr' => ['class' => 'form-control w-full'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FermetureGymnase::class,
            'lieux' => null,
        ]);
        $resolver->setAllowedTypes('lieux', ['null', 'array']);
    }
}
