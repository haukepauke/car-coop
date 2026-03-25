<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Message\Event\InvitationAcceptedEvent;
use App\Repository\CarRepository;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(
        EmailVerifier $emailVerifier,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
        $this->emailVerifier = $emailVerifier;
    }

    private function isBotSubmission(Request $request, \Symfony\Component\Form\FormInterface $form): bool
    {
        // Honeypot: the website field must be empty
        if ('' !== (string) $form->get('website')->getData()) {
            $this->logger->info('Bot registration blocked (honeypot)', ['ip' => $request->getClientIp()]);
            return true;
        }

        // Timing: form rendered time stored in session; reject if submitted under 3 seconds
        $session = $request->getSession();
        $renderedAt = $session->get('registration_form_at');
        if ($renderedAt !== null && (time() - $renderedAt) < 3) {
            $this->logger->info('Bot registration blocked (too fast)', ['ip' => $request->getClientIp()]);
            return true;
        }

        return false;
    }

    #[Route(
        path: '/{_locale}/register',
        name: 'app_register',
        requirements: [
            '_locale' => 'en|de|nl|fr|es|pl',
        ],
    )]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // user is already authenticated: redirect them to their target page instead
            $this->addFlash('error', $translator->trans('registration.already_logged_in'));
            return $this->redirectToRoute('app_car_show');
        }
        $user = new User();
        $user->setLocale($request->getLocale());
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isBotSubmission($request, $form)) {
                return $this->redirectToRoute('app_login');
            }

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // set random color to start with
            $user->setColor('#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT));
            $user->setNotifiedOnEvents(false);
            $user->setNotifiedOnOwnEvents(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            $request->getSession()->set('check_email_address', $user->getEmail());

            return $this->redirectToRoute('app_register_check_email');
        }

        $request->getSession()->set('registration_form_at', time());

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/register/invited/{hash}', name: 'app_register_invited')]
    public function registerInvited(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        InvitationRepository $inviteRepo,
        CarRepository $carRepo,
        MessageBusInterface $messageBus,
        UserAuthenticatorInterface $userAuthenticator,
        \App\Security\LoginFormAuthenticator $authenticator,
        TranslatorInterface $translator,
        $hash
    ): Response {
        $invite = $inviteRepo->findOneByHash($hash);
        if (null === $invite) {
            $this->addFlash('error', $translator->trans('registration.invitation_not_found'));

            return $this->redirectToRoute('app_homepage');
        }
        $userType = $invite->getUserType();

        $car = $userType->getCar();
        $carObj = $carRepo->find($car);

        $user = new User();
        $user->addUserType($userType);
        $user->setEmail($invite->getEmail());
        $form = $this->createForm(RegistrationFormType::class, $user, ['email_locked' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isBotSubmission($request, $form)) {
                return $this->redirectToRoute('app_login');
            }

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // set random color to start with
            $user->setColor('#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT));
            $user->setNotifiedOnEvents(false);
            $user->setNotifiedOnOwnEvents(false);
            // invited users are trusted — mark as verified immediately
            $user->setIsVerified(true);

            $carId = $car->getId();

            $entityManager->persist($user);
            $entityManager->remove($invite);
            $entityManager->flush();

            $messageBus->dispatch(new InvitationAcceptedEvent($user->getId(), $carId));

            // log the user in directly
            return $userAuthenticator->authenticateUser($user, $authenticator, $request)
                ?? $this->redirectToRoute('app_car_show');
        }

        $request->getSession()->set('registration_form_at', time());

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'car' => $carObj,
        ]);
    }

    #[Route('/register/check-email', name: 'app_register_check_email')]
    public function checkEmail(Request $request): Response
    {
        $email = $request->getSession()->get('check_email_address');

        return $this->render('registration/check_email.html.twig', [
            'email' => $email,
        ]);
    }

    #[Route('/register/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, UserRepository $userRepository): Response
    {
        if (!$this->isCsrfTokenValid('resend_verification', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = $request->request->get('email', '');
        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user && !$user->isVerified()) {
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );
        }

        // Always store email and redirect (don't reveal whether account exists)
        $request->getSession()->set('check_email_address', $email);

        return $this->redirectToRoute('app_register_check_email');
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        /** @var App\Entity\User */
        $user = null;

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $user = $this->emailVerifier->handleEmailConfirmation($request, $userRepository);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_homepage');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', $translator->trans('registration.email_verified'));

        if ($user->getUserTypes()->isEmpty()) {
            // A user without user types is new without car, redirect to the car creation page
            return $this->redirectToRoute('app_car_new');
        }

        // All other users are redirected to the Car page
        return $this->redirectToRoute('app_car_show');
    }
}
