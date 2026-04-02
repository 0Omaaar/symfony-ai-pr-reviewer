<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UnsubscribeController extends AbstractController
{
    #[Route('/unsubscribe/{token}', name: 'app_unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        EntityManagerInterface $em,
        ParameterBagInterface $params,
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['unsubscribeToken' => $token]);

        $frontUrl = rtrim(trim((string) $params->get('pr_alert.front_url')), '/');

        if ($user === null) {
            return $frontUrl !== ''
                ? new RedirectResponse($frontUrl . '/unsubscribe?status=invalid')
                : new Response('Invalid or expired unsubscribe link.', Response::HTTP_NOT_FOUND);
        }

        if ($user->isEmailNotificationsEnabled()) {
            $user->setEmailNotificationsEnabled(false);
            $em->flush();
        }

        return $frontUrl !== ''
            ? new RedirectResponse($frontUrl . '/unsubscribe?status=success')
            : new Response('You have been unsubscribed from PR alert emails.', Response::HTTP_OK);
    }
}
