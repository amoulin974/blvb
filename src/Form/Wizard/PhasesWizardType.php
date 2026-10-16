<?php

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Étape 3 du wizard : les phases de la saison, dans l'ordre (données : ['phases' => list<Phase>]). */
class PhasesWizardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phases', CollectionType::class, [
            'entry_type' => PhaseWizardType::class,
            'entry_options' => ['label' => false, 'gymnases' => $options['gymnases']],
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Gymnases proposés pour la journée des finales (identifiant => nom)
        $resolver->setDefaults(['gymnases' => []]);
        $resolver->setAllowedTypes('gymnases', 'array');
    }
}
