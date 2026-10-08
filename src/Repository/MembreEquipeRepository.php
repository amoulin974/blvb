<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\MembreEquipe;
use App\Entity\Saison;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MembreEquipe>
 */
class MembreEquipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MembreEquipe::class);
    }

    /**
     * Récupère la composition d'une équipe pour une saison donnée (capitaine en premier).
     *
     * @return MembreEquipe[]
     */
    public function findRosterBySaison(Equipe $equipe, Saison $saison): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.joueur', 'j')->addSelect('j')
            ->andWhere('m.equipe = :equipe')
            ->andWhere('m.saison = :saison')
            ->setParameter('equipe', $equipe)
            ->setParameter('saison', $saison)
            ->orderBy('m.capitaine', 'DESC')
            ->addOrderBy('j.nom', 'ASC')
            ->addOrderBy('j.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCapitaine(Equipe $equipe, Saison $saison): ?MembreEquipe
    {
        return $this->findOneBy(['equipe' => $equipe, 'saison' => $saison, 'capitaine' => true]);
    }

    /**
     * Les lignes d'appartenance où cet utilisateur est désigné capitaine (toutes équipes/saisons confondues),
     * utile pour afficher "mes équipes par saison" sur le profil.
     *
     * @return MembreEquipe[]
     */
    public function findMembresCapitaine(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('e', 's')
            ->innerJoin('m.joueur', 'j')
            ->innerJoin('m.equipe', 'e')
            ->innerJoin('m.saison', 's')
            ->andWhere('j.user = :user')
            ->andWhere('m.capitaine = true')
            ->setParameter('user', $user)
            ->orderBy('s.date_debut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Equipes dont le Joueur lié à cet utilisateur est capitaine (pour une saison donnée, ou toutes saisons).
     *
     * @return Equipe[]
     */
    public function findEquipesOuCapitaine(User $user, ?Saison $saison = null): array
    {
        // Equipe comme racine de la requête (plutôt que MembreEquipe) pour pouvoir
        // sélectionner "e" directement : DQL interdit de sélectionner une entité jointe
        // sans que la racine du FROM en fasse partie.
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT e')
            ->from(Equipe::class, 'e')
            ->innerJoin('e.membres', 'm')
            ->innerJoin('m.joueur', 'j')
            ->andWhere('j.user = :user')
            ->andWhere('m.capitaine = true')
            ->setParameter('user', $user);

        if ($saison !== null) {
            $qb->andWhere('m.saison = :saison')->setParameter('saison', $saison);
        }

        return $qb->getQuery()->getResult();
    }

    public function estCapitaine(User $user, Equipe $equipe, Saison $saison): bool
    {
        $membre = $this->createQueryBuilder('m')
            ->innerJoin('m.joueur', 'j')
            ->andWhere('j.user = :user')
            ->andWhere('m.equipe = :equipe')
            ->andWhere('m.saison = :saison')
            ->andWhere('m.capitaine = true')
            ->setParameter('user', $user)
            ->setParameter('equipe', $equipe)
            ->setParameter('saison', $saison)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $membre !== null;
    }

    /**
     * Cet utilisateur fait-il partie de la composition de l'équipe pour la saison (capitaine ou simple joueur) ?
     */
    public function estMembre(User $user, Equipe $equipe, Saison $saison): bool
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.joueur', 'j')
            ->andWhere('j.user = :user')
            ->andWhere('m.equipe = :equipe')
            ->andWhere('m.saison = :saison')
            ->setParameter('user', $user)
            ->setParameter('equipe', $equipe)
            ->setParameter('saison', $saison)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Désigne $nouveauCapitaine comme seul capitaine de l'équipe pour cette saison
     * (démarque les éventuels autres capitaines au passage).
     */
    public function definirCapitaine(Equipe $equipe, Saison $saison, MembreEquipe $nouveauCapitaine): void
    {
        $this->getEntityManager()->wrapInTransaction(function () use ($equipe, $saison, $nouveauCapitaine) {
            $this->getEntityManager()->createQueryBuilder()
                ->update(MembreEquipe::class, 'm')
                ->set('m.capitaine', ':faux')
                ->where('m.equipe = :equipe')
                ->andWhere('m.saison = :saison')
                ->andWhere('m.id != :id')
                ->setParameter('faux', false)
                ->setParameter('equipe', $equipe)
                ->setParameter('saison', $saison)
                ->setParameter('id', $nouveauCapitaine->getId())
                ->getQuery()
                ->execute();

            $nouveauCapitaine->setCapitaine(true);
            $this->getEntityManager()->flush();
        });
    }
}
