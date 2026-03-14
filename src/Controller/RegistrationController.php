<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
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
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier, private readonly LoggerInterface $logger)
    {
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
            '_locale' => 'en|de',
        ],
    )]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // user is already authenticated: redirect them to their target page instead
            $this->addFlash('error', 'User already logged in!');
            return $this->redirectToRoute('app_car_show');
        }
        $user = new User();
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
            $user->setNotifiedOnEvents(true);
            $user->setNotifiedOnOwnEvents(false);

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('webmaster@car-coop.net', 'Car Coop Mail Bot'))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            return $this->redirectToRoute('app_car_new');
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
        $hash
    ): Response {
        $invite = $inviteRepo->findOneByHash($hash);
        if (null === $invite) {
            $this->addFlash('error', 'Invitation not found (or has expired)!');

            return $this->redirectToRoute('app_homepage');
        }
        $userType = $invite->getUserType();

        $car = $userType->getCar();
        $carObj = $carRepo->find($car);

        $user = new User();
        $user->addUserType($userType);
        $user->setEmail($invite->getEmail());
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
            $user->setNotifiedOnEvents(true);
            $user->setNotifiedOnOwnEvents(false);

            $entityManager->persist($user);
            $entityManager->remove($invite);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('webmaster@car-coop.net', 'Car Coop Mail Bot'))
                    ->to($user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            return $this->redirectToRoute('app_homepage');
        }

        $request->getSession()->set('registration_form_at', time());

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'car' => $carObj,
        ]);
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
        $this->addFlash('success', 'Your email address has been verified.');

        if ($user->getUserTypes()->isEmpty()) {
            // A user without user types is new without car, redirect to the car creation page
            return $this->redirectToRoute('app_car_new');
        }

        // All other users are redirected to the Car page
        return $this->redirectToRoute('app_car_show');
    }
}
