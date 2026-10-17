<?php

namespace App\Service;

use App\Entity\Equipe;
use App\Entity\Partie;
use App\Entity\Saison;
use App\Repository\MembreEquipeRepository;
use App\Repository\PartieRepository;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Rappel par e-mail des scores non saisis : deux jours (par défaut) après la date d'un match de la saison
 * courante sans aucun score, on prévient ceux qui ont le droit de le saisir (SaisieScoreService) :
 *  - le capitaine de chacune des deux équipes ;
 *  - les responsables du club de chacune des deux équipes, désignés pour la saison.
 *
 * Un seul e-mail par destinataire, qui récapitule tous ses matchs à renseigner. Chaque match n'est rappelé
 * qu'une fois (Partie::rappelScoreEnvoyeLe) : relancer la commande ne renvoie rien. Un match dont personne
 * n'a d'adresse n'est pas marqué, il sera rappelé dès qu'un capitaine ou un responsable est désigné.
 */
class RappelScoreService
{
    public const DELAI_PAR_DEFAUT_JOURS = 2;

    public function __construct(
        private readonly PartieRepository $partieRepository,
        private readonly SaisonRepository $saisonRepository,
        private readonly MembreEquipeRepository $membreEquipeRepository,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Calcule les rappels à envoyer, sans rien envoyer ni enregistrer.
     *
     * @return array<string, array{matchs: Partie[]}> par adresse e-mail (en minuscules)
     */
    public function preparer(\DateTimeImmutable $maintenant, int $delaiJours = self::DELAI_PAR_DEFAUT_JOURS): array
    {
        return $this->regrouper($this->matchsARappeler($maintenant, $delaiJours));
    }

    /**
     * Envoie les rappels et marque les matchs concernés.
     *
     * Un e-mail refusé par le serveur n'interrompt pas les autres : son adresse est renvoyée dans « echecs »
     * et un match n'est marqué que si au moins un de ses destinataires a été prévenu.
     *
     * @return array{mails: int, matchs: int, echecs: string[]} e-mails envoyés, matchs rappelés, adresses en échec
     */
    public function envoyer(\DateTimeImmutable $maintenant, int $delaiJours = self::DELAI_PAR_DEFAUT_JOURS): array
    {
        $rappeles = [];
        $echecs = [];
        $mails = 0;
        foreach ($this->preparer($maintenant, $delaiJours) as $adresse => $rappel) {
            try {
                $this->mailer->send((new TemplatedEmail())
                    ->to($adresse)
                    ->subject(\count($rappel['matchs']) === 1
                        ? 'BLVB : un score à saisir'
                        : \sprintf('BLVB : %d scores à saisir', \count($rappel['matchs'])))
                    ->htmlTemplate('email/rappel_score.html.twig')
                    ->context(['matchs' => $rappel['matchs'], 'delai' => $delaiJours]));
            } catch (TransportExceptionInterface) {
                $echecs[] = $adresse;
                continue;
            }
            ++$mails;
            foreach ($rappel['matchs'] as $match) {
                $rappeles[$match->getId()] = $match;
            }
        }

        foreach ($rappeles as $match) {
            $match->setRappelScoreEnvoyeLe($maintenant);
        }
        $this->entityManager->flush();

        return ['mails' => $mails, 'matchs' => \count($rappeles), 'echecs' => $echecs];
    }

    /** @return Partie[] */
    private function matchsARappeler(\DateTimeImmutable $maintenant, int $delaiJours): array
    {
        $saison = $this->saisonRepository->findCourante();
        if ($saison === null) {
            return [];
        }

        return $this->partieRepository->findSansScoreARappeler($saison, $maintenant->modify(\sprintf('-%d days', $delaiJours)));
    }

    /**
     * @param Partie[] $matchs
     *
     * @return array<string, array{matchs: Partie[]}>
     */
    private function regrouper(array $matchs): array
    {
        $parAdresse = [];
        foreach ($matchs as $match) {
            $saison = $match->getPoule()?->getPhase()?->getSaison();
            foreach ($this->adressesDe($match, $saison) as $adresse) {
                $parAdresse[$adresse]['matchs'][] = $match;
            }
        }
        ksort($parAdresse);

        return $parAdresse;
    }

    /** @return string[] adresses distinctes (en minuscules) des capitaines et responsables de club des deux équipes */
    private function adressesDe(Partie $match, ?Saison $saison): array
    {
        if ($saison === null) {
            return [];
        }

        $adresses = [];
        foreach ([$match->getIdEquipeRecoit(), $match->getIdEquipeDeplace()] as $equipe) {
            if (!$equipe instanceof Equipe) {
                continue;
            }
            $joueur = $this->membreEquipeRepository->findCapitaine($equipe, $saison)?->getJoueur();
            $adresses[] = $joueur?->getEmail() ?: $joueur?->getUser()?->getEmail();
            foreach ($equipe->getClub()?->getResponsables($saison) ?? [] as $responsable) {
                $adresses[] = $responsable->getEmail();
            }
        }

        return array_values(array_unique(array_map(
            static fn (string $adresse): string => mb_strtolower(trim($adresse)),
            array_filter($adresses, static fn (?string $adresse): bool => $adresse !== null && trim($adresse) !== ''),
        )));
    }
}
