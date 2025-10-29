<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\SecurityBundle\Security;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;
    private MailerInterface $mailer;
    private TokenStorageInterface $tokenStorage;

 
    public function __construct(EmailVerifier $emailVerifier, MailerInterface $mailer, TokenStorageInterface $tokenStorage)
    {
        $this->emailVerifier = $emailVerifier;
        $this->mailer = $mailer;
        $this->tokenStorage  = $tokenStorage;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, 
    UserPasswordHasherInterface $userPasswordHasher, 
    EntityManagerInterface $entityManager, 
   
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            //Connexion automatique de l'utilisateur après son inscription
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

        

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('tech@vblc.fr', 'inscription@blvb'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // do anything else you need here, like send an email

            return $this->redirectToRoute('registerok');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            /** @var User $user */
            // Récupérer l'id depuis le lien signé
        
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();


        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Votre email a été vérifiée avec succès.');

        return $this->redirectToRoute('front_index');
    }

    #[Route('/registerok', name: 'registerok')]
    public function registerok(): Response
    {
        //Rajouter ces deux lignes dans toutes les fonctions du front pour initialiser le menu des saisons
       
        return $this->render('registration/registerOk.html.twig', [
            
        ]);
    }
  

}
