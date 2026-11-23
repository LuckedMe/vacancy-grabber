<?php

declare(strict_types=1);

namespace App\Application\Dashboard\MessageHandler\Query;

use App\Application\Dashboard\DTO\DashboardCollectionDTO;
use App\Application\Dashboard\Message\Query\GetDashboardCollectionQuery;
use App\Application\Dashboard\Message\Query\GetResumeSentDashboardQuery;
use App\Application\Dashboard\Message\Query\GetVacancyStatusDashboardQuery;
use App\Application\Shared\Bus\MessageBus;
use App\Application\Shared\Bus\QueryHandlerInterface;

final readonly class GetDashboardCollectionHandler implements QueryHandlerInterface
{
    public function __construct(
        private MessageBus $messageBus,
    ) {
    }

    public function __invoke(GetDashboardCollectionQuery $query): DashboardCollectionDTO
    {
        $vacancyStatus = $this->messageBus
            ->handleQuery(new GetVacancyStatusDashboardQuery(userId: $query->userId));

        $sentResumeToAllResumeZoomable = $this->messageBus
            ->handleQuery(new GetResumeSentDashboardQuery(userId: $query->userId));

        return new DashboardCollectionDTO(
            vacancyStatus: $vacancyStatus,
            sentResumeToAllResumeZoomable: $sentResumeToAllResumeZoomable
        );
    }
}
