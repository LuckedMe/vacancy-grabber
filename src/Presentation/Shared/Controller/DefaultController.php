<?php

declare(strict_types=1);

namespace App\Presentation\Shared\Controller;

use LuckedMe\CmsClientBundle\Service\PageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route(
        path: '',
        name: 'web.homepage',
        options: ['expose' => true],
        methods: ['GET'],
    )]
    public function index(PageService $pageService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('web.dashboard');
        }

        $page = $pageService->getPage('homepage');

        return $this->render('@general_templates/web/homepage.html.twig', [
            'page'         => $page,
            'header_title' => $page?->title,
        ]);
    }
}
