<?php

declare(strict_types=1);

namespace Yishaq\Server\Controllers;

use InvalidArgumentException;
use RuntimeException;
use Yishaq\Server\Core\Request;
use Yishaq\Server\Core\Response;
use Yishaq\Server\Services\ScheduleService;

final class ScheduleController
{
    private ScheduleService $schedules;

    public function __construct(?ScheduleService $schedules = null)
    {
        $this->schedules = $schedules ?? new ScheduleService();
    }

    public function publicIndex(Request $request, Response $response): void
    {
        $response->json([
            'success' => true,
            'message' => 'Schedules fetched.',
            'data' => ['schedules' => $this->schedules->visible()],
        ]);
    }

    public function adminIndex(Request $request, Response $response, array $user): void
    {
        $response->json([
            'success' => true,
            'message' => 'Schedules fetched.',
            'data' => ['schedules' => $this->schedules->all()],
        ]);
    }

    public function store(Request $request, Response $response, array $user): void
    {
        try {
            $schedule = $this->schedules->create($request->json());
            $response->json([
                'success' => true,
                'message' => 'Schedule created.',
                'data' => ['schedule' => $schedule],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            $this->error($response, $exception->getMessage(), 422);
        }
    }

    public function update(Request $request, Response $response, array $user, array $params): void
    {
        try {
            $schedule = $this->schedules->update((int) ($params['id'] ?? 0), $request->json());
            $response->json([
                'success' => true,
                'message' => 'Schedule updated.',
                'data' => ['schedule' => $schedule],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->error($response, $exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            $this->error($response, $exception->getMessage(), 404);
        }
    }

    public function cancel(Request $request, Response $response, array $user, array $params): void
    {
        try {
            $schedule = $this->schedules->cancel((int) ($params['id'] ?? 0));
            $response->json([
                'success' => true,
                'message' => 'Schedule cancelled.',
                'data' => ['schedule' => $schedule],
            ]);
        } catch (RuntimeException $exception) {
            $this->error($response, $exception->getMessage(), 404);
        }
    }

    private function error(Response $response, string $message, int $status): void
    {
        $response->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
