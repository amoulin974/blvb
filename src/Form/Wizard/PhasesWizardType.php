<?php

namespace App\Form\Wizard;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

/** Étape 3 du wizard : les phases de la saison, dans l'ordre (données : ['phases' => list<Phase>]). */
class PhasesWizardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('phases', CollectionType::class, [
            'entry_type' => PhaseWizardType::class,
            'entry_options' => ['label' => false],
            'label' => false,
        ]);
    }
}
