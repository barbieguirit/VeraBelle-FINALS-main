<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('', name: 'app_admin_activity_logs', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $username = $request->query->get('username', '');
        $action = $request->query->get('action', '');
        $startDate = $request->query->get('start_date', '');
        $endDate = $request->query->get('end_date', '');

        $startDateTime = $startDate ? new \DateTime($startDate) : null;
        $endDateTime = $endDate ? new \DateTime($endDate . ' 23:59:59') : null;

        $logs = $activityLogRepository->findByFilters(
            $username ?: null,
            $action ?: null,
            $startDateTime,
            $endDateTime
        );

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $logs,
            'filters' => [
                'username' => $username,
                'action' => $action,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }
}
