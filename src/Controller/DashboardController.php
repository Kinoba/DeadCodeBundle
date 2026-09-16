<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Controller;

use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function dashboard(CoverageReporter $reporter): Response
    {
        $data = $reporter->getDashboardData();

        return $this->render('@DeadCode/dashboard.html.twig', [
            'files' => $data['files'],
            'totalLines' => $data['totalLines'],
            'coveredLines' => $data['coveredLines'],
            'coveragePercentage' => $data['coveragePercentage'],
        ]);
    }

    public function api(CoverageReporter $reporter): Response
    {
        return $this->json($reporter->getDashboardData());
    }

    public function clear(CoverageReporter $reporter): Response
    {
        $reporter->clear();

        return $this->redirectToRoute('dead_code_dashboard');
    }
}
