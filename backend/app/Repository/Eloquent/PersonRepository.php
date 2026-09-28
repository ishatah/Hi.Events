<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\PersonDomainObject;
use HiEvents\Models\Person;
use HiEvents\Repository\Interfaces\PersonRepositoryInterface;

/**
 * @extends BaseRepository<PersonDomainObject>
 */
class PersonRepository extends BaseRepository implements PersonRepositoryInterface
{
    protected function getModel(): string
    {
        return Person::class;
    }

    public function getDomainObject(): string
    {
        return PersonDomainObject::class;
    }
}
