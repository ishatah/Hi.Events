<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\BadgeTemplateDomainObject;
use HiEvents\Models\BadgeTemplate;
use HiEvents\Repository\Interfaces\BadgeTemplateRepositoryInterface;

/**
 * @extends BaseRepository<BadgeTemplateDomainObject>
 */
class BadgeTemplateRepository extends BaseRepository implements BadgeTemplateRepositoryInterface
{
    protected function getModel(): string
    {
        return BadgeTemplate::class;
    }

    public function getDomainObject(): string
    {
        return BadgeTemplateDomainObject::class;
    }
}
