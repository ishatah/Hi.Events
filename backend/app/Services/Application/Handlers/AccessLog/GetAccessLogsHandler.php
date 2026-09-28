<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\AccessLog;

use HiEvents\DomainObjects\Generated\AccessLogDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\AccessLogRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAccessLogsHandler
{
    public function __construct(
        private readonly AccessLogRepositoryInterface $accessLogRepository,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $queryParamsDTO): LengthAwarePaginator
    {
        return $this->accessLogRepository->paginateWhere(
            where: [AccessLogDomainObjectAbstract::EVENT_ID => $eventId],
            limit: $queryParamsDTO->per_page,
            page: $queryParamsDTO->page,
        );
    }
}
