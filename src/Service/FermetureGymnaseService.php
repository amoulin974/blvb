<?php

namespace App\Service;

use App\Entity\FermetureGymnase;
use App\Entity\Lieu;
use App\Entity\Partie;
use App\Repository\ParametresSiteRepository;
use App\Repository\PartieRepository;
use App\Repository\UserRepository;

/**
 * Règles de saisie d'une fermeture de gymnase (D60) : matchs programmés pendant la période et textes qui les citent.
 * Un responsable de club est refusé s'il y en a ; un administrateur enregistre, puis replace ces matchs.
 */
class FermetureGymnaseService
{
    public function __construct(
        private readonly PartieRepository $parties,
        private readonly UserRepository $users,
        private readonly ParametresSiteRepository $parametres,
    ) {
    }

    /** @return list<string> une phrase par match sans score programmé dans le gymnase pendant la période */
    public function matchsConcernes(FermetureGymnase $fermeture): array
    {
        return array_map(
            fn (Partie $partie) => $this->texteMatch($partie),
            $this->parties->matchsProgrammes($fermeture->getLieu(), $fermeture->getDateDebut(), $fermeture->getDateFin())
        );
    }

    /** @param list<string> $matchs */
    public function avertissementAdmin(array $matchs): string
    {
        $n = \count($matchs);

        return sprintf(
            '%s dans ce gymnase pendant la fermeture : %s. Pensez à %s depuis la mise en place de la phase.',
            $n > 1 ? $n.' matchs sont programmés' : '1 match est programmé',
            implode(' ; ', $matchs),
            $n > 1 ? 'les replacer' : 'le replacer'
        );
    }

    /**
     * Adresses de connexion des administrateurs, à qui le responsable refusé écrit.
     *
     * @return list<string>
     */
    public function emailsContact(): array
    {
        $contact = $this->parametres->courants()->getAdresseContact();

        return $contact !== null ? [$contact] : $this->users->emailsAdmins();
    }

    /**
     * Placement à la main (D60) : pourquoi ce match ne peut pas aller là, ou null. Seul un changement de gymnase ou de jour
     * est contrôlé : un match déjà dans un gymnase fermé reste modifiable (heure, score). Un match au score saisi n'est pas
     * contrôlé : il a été joué là.
     */
    public function refusPlacement(Partie $partie, ?Lieu $lieuAvant, ?\DateTimeInterface $dateAvant): ?string
    {
        $lieu = $partie->getLieu();
        $date = $partie->getDate();
        if ($lieu === null || $date === null || ($partie->getNbSetGagnantReception() !== null && $partie->getNbSetGagnantDeplacement() !== null)) {
            return null;
        }
        if ($lieu === $lieuAvant && $date->format('Y-m-d') === $dateAvant?->format('Y-m-d')) {
            return null;
        }
        $fermeture = $lieu->fermeLe($date);
        if ($fermeture === null) {
            return null;
        }
        $motif = $fermeture->getMotif();

        return sprintf(
            'Le gymnase %s est fermé le %s%s. Choisissez une autre date ou un autre gymnase.%s',
            $lieu->getNom(),
            $date->format('d/m/Y'),
            $motif !== null ? sprintf(' (%s)', $motif) : '',
            // Une fermeture terminée ne figure plus dans la liste d'où on la retire
            $fermeture->getDateFin()->format('Y-m-d') >= date('Y-m-d') ? ' Si la fermeture est annulée, retirez-la d\'abord.' : ''
        );
    }

    /**
     * Lien d'e-mail prérempli vers les administrateurs : le responsable n'a rien à ressaisir.
     *
     * @param list<string> $matchs
     * @param list<string> $emails
     */
    public function lienContactAdmins(FermetureGymnase $fermeture, array $matchs, array $emails): ?string
    {
        if ($emails === []) {
            return null;
        }
        $nomLieu = (string) $fermeture->getLieu()->getNom();
        $plusieurs = \count($matchs) > 1;
        $corps = sprintf(
            "Bonjour,\n\nLe gymnase %s sera fermé du %s au %s (%s).\n\n%s pendant cette période :\n- %s\n\nPouvez-vous %s déplacer et enregistrer la fermeture ?\n\nMerci.",
            $nomLieu,
            $fermeture->getDateDebut()->format('d/m/Y'),
            $fermeture->getDateFin()->format('d/m/Y'),
            $fermeture->getMotif(),
            $plusieurs ? 'Matchs programmés' : 'Match programmé',
            implode("\n- ", $matchs),
            $plusieurs ? 'les' : 'le'
        );

        return 'mailto:'.implode(',', $emails)
            .'?subject='.rawurlencode('Fermeture du gymnase '.$nomLieu)
            .'&body='.rawurlencode($corps);
    }

    private function texteMatch(Partie $partie): string
    {
        // Une saison pas encore publiée ne dévoile pas ses rencontres
        if ($partie->getPoule()?->getPhase()?->getSaison()?->isBrouillon()) {
            return sprintf('%s, match d\'une saison en préparation', $partie->getDate()->format('d/m/Y'));
        }

        return sprintf('%s, %s contre %s', $partie->getDate()->format('d/m/Y'), $partie->getNomReception(), $partie->getNomDeplacement());
    }
}
